<?php

namespace App\Http\Controllers;

use App\Contracts\PaymentGateway;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventBooking;
use App\Models\EventTicketTier;
use App\Models\PaymentEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EventBookingController extends Controller
{
    public function store(Request $request, Event $event, PaymentGateway $gateway): RedirectResponse
    {
        $validated = $request->validate([
            'ticket_tier_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:5'],
            'payment_method' => ['required', 'string', 'in:free,mock_bkash,mock_nagad,mock_card'],
            'idempotency_key' => ['nullable', 'string', 'max:80'],
        ]);

        $idempotencyKey = $validated['idempotency_key'] ?? Str::uuid()->toString();
        $existing = EventBooking::where('idempotency_key', $idempotencyKey)
            ->where('user_id', auth()->id())
            ->first();
        if ($existing) {
            return redirect()->route('events.bookings.show', $existing->booking_reference)
                ->with('status', __('Existing booking returned.'));
        }

        $booking = DB::transaction(function () use ($validated, $event, $idempotencyKey) {
            /** @var EventTicketTier|null $tier */
            $tier = EventTicketTier::where('id', $validated['ticket_tier_id'])
                ->where('event_id', $event->id)
                ->lockForUpdate()
                ->first();

            if (! $tier) {
                throw ValidationException::withMessages([
                    'ticket_tier_id' => __('Invalid ticket tier selected for this event.'),
                ]);
            }

            $requestedQty = (int) $validated['quantity'];

            if ($requestedQty > $tier->max_per_user) {
                throw ValidationException::withMessages([
                    'quantity' => __('You cannot book more than :max tickets per order.', ['max' => $tier->max_per_user]),
                ]);
            }

            if (($tier->booked_count + $requestedQty) > $tier->capacity) {
                $remaining = max(0, $tier->capacity - $tier->booked_count);
                throw ValidationException::withMessages([
                    'quantity' => __('Only :count tickets remaining for this tier.', ['count' => $remaining]),
                ]);
            }

            $unitPrice = (float) $tier->price;
            $totalPrice = $unitPrice * $requestedQty;
            if ($totalPrice > 0 && $validated['payment_method'] === 'free') {
                throw ValidationException::withMessages([
                    'payment_method' => __('Paid ticket tiers require a configured payment method.'),
                ]);
            }
            if ($totalPrice > 0 && config('nightlife.payments_driver') === 'none') {
                throw ValidationException::withMessages([
                    'payment_method' => __('Paid ticketing is not enabled. This event cannot be booked until a verified payment provider is configured.'),
                ]);
            }
            $paymentStatus = $totalPrice <= 0 ? 'free' : 'pending';
            $status = $totalPrice <= 0 ? 'confirmed' : 'pending_payment';

            $tier->increment('booked_count', $requestedQty);

            return EventBooking::create([
                'user_id' => auth()->id(),
                'event_id' => $event->id,
                'ticket_tier_id' => $tier->id,
                'quantity' => $requestedQty,
                'unit_price' => $unitPrice,
                'total_price' => $totalPrice,
                'payment_method' => $validated['payment_method'],
                'payment_status' => $paymentStatus,
                'status' => $status,
                'currency' => 'BDT',
                'idempotency_key' => $idempotencyKey,
                'payment_expires_at' => $totalPrice > 0 ? now()->addMinutes(10) : null,
            ]);
        });

        if ($booking->total_price > 0) {
            $gatewayResult = $gateway->createPending($booking);
            if (($gatewayResult['status'] ?? null) === 'ticketing_not_enabled') {
                DB::transaction(function () use ($booking) {
                    $booking->update(['status' => 'cancelled', 'payment_status' => 'cancelled']);
                    $booking->ticketTier()->decrement('booked_count', $booking->quantity);
                });

                return redirect()->back()->withErrors([
                    'payment_method' => __('Paid ticketing is not enabled. This event cannot be booked until a verified payment provider is configured.'),
                ]);
            }
        }

        return redirect()
            ->route('events.bookings.show', $booking->booking_reference)
            ->with('status', __('Ticket confirmed! Here is your NightLifeBD Event Pass.'));
    }

    public function show(string $reference): View
    {
        $booking = EventBooking::where('booking_reference', $reference)
            ->with(['event.venue.scores', 'ticketTier', 'user'])
            ->firstOrFail();

        $user = auth()->user();
        $isOwner = $user && $user->id === $booking->user_id;
        $isOrganizer = $user && $booking->event->organizer_id === $user->id;
        $isStaff = $user && $user->isStaff();

        abort_unless($isOwner || $isOrganizer || $isStaff, 403);

        return view('events.pass', [
            'booking' => $booking,
            'event' => $booking->event,
            'tier' => $booking->ticketTier,
        ]);
    }

    public function mockWebhook(Request $request, PaymentGateway $gateway)
    {
        if (! config('nightlife.demo_enabled') || config('nightlife.payments_driver') !== 'mock') {
            return response()->json(['status' => 'rejected', 'message' => 'Mock payments unavailable'], 404);
        }

        $result = $gateway->handleWebhook($request);

        return response()->json($result, ($result['status'] ?? null) === 'rejected' ? 422 : 200);
    }

    public function mine(): View
    {
        $bookings = auth()->user()
            ->eventBookings()
            ->with(['event.venue', 'ticketTier'])
            ->latest()
            ->paginate(10);

        return view('events.my-bookings', [
            'bookings' => $bookings,
        ]);
    }

    public function cancel(Request $request, EventBooking $booking): RedirectResponse
    {
        abort_unless(auth()->id() === $booking->user_id, 403);

        if ($booking->isCancelled()) {
            return back()->with('error', __('Booking is already cancelled.'));
        }

        if ($booking->isCheckedIn()) {
            return back()->with('error', __('Cannot cancel a ticket that has already been checked in.'));
        }

        if ($booking->event->starts_at->isPast()) {
            return back()->with('error', __('Cannot cancel ticket for an event that has already started.'));
        }

        DB::transaction(function () use ($booking, $request) {
            $lockedBooking = EventBooking::where('id', $booking->id)->lockForUpdate()->firstOrFail();

            if ($lockedBooking->isCancelled() || $lockedBooking->isCheckedIn()) {
                return;
            }

            $isPaid = ($lockedBooking->total_price > 0 && $lockedBooking->payment_status === 'paid');
            $newPaymentStatus = $isPaid ? 'refund_pending' : 'cancelled';

            $lockedBooking->update([
                'status' => 'cancelled',
                'payment_status' => $newPaymentStatus,
            ]);

            if ($lockedBooking->ticket_tier_id) {
                $tier = EventTicketTier::where('id', $lockedBooking->ticket_tier_id)->lockForUpdate()->first();
                if ($tier) {
                    $decrementAmount = min($tier->booked_count, $lockedBooking->quantity);
                    if ($decrementAmount > 0) {
                        $tier->decrement('booked_count', $decrementAmount);
                    }
                }
            }

            PaymentEvent::create([
                'event_booking_id' => $lockedBooking->id,
                'provider' => $lockedBooking->payment_method ?? 'system',
                'event_type' => $isPaid ? 'payment.refund_requested' : 'payment.cancelled',
                'provider_reference' => $lockedBooking->provider_reference ?? $lockedBooking->booking_reference,
                'signature_status' => 'internal',
                'amount' => $lockedBooking->total_price,
                'currency' => $lockedBooking->currency ?? 'BDT',
                'payload' => [
                    'reason' => 'user_cancelled',
                    'cancelled_by' => $request->user()?->id ?? auth()->id(),
                    'previous_payment_status' => $lockedBooking->getOriginal('payment_status'),
                    'previous_booking_status' => $lockedBooking->getOriginal('status'),
                ],
            ]);

            AuditLog::record(
                $request->user()?->id ?? auth()->id(),
                'booking.cancelled',
                [
                    'booking_id' => $lockedBooking->id,
                    'booking_reference' => $lockedBooking->booking_reference,
                    'refund_initiated' => $isPaid,
                    'payment_status' => $newPaymentStatus,
                    'total_price' => $lockedBooking->total_price,
                    'quantity' => $lockedBooking->quantity,
                ]
            );
        }, 3);

        return back()->with('status', __('Ticket reservation cancelled successfully.'));
    }
}
