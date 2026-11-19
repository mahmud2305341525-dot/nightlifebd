<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventBooking;
use App\Models\EventTicketTier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EventBookingController extends Controller
{
    public function store(Request $request, Event $event): RedirectResponse
    {
        $validated = $request->validate([
            'ticket_tier_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:5'],
            'payment_method' => ['required', 'string', 'in:free,mock_bkash,mock_nagad,mock_card'],
        ]);

        $booking = DB::transaction(function () use ($validated, $event) {
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
            $paymentStatus = $totalPrice <= 0 ? 'free' : 'paid';

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
                'status' => 'confirmed',
            ]);
        });

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

    public function cancel(EventBooking $booking): RedirectResponse
    {
        abort_unless(auth()->id() === $booking->user_id, 403);

        if ($booking->status === 'cancelled') {
            return back()->with('error', __('Booking is already cancelled.'));
        }

        if ($booking->event->starts_at->isPast()) {
            return back()->with('error', __('Cannot cancel ticket for an event that has already started.'));
        }

        DB::transaction(function () use ($booking) {
            $booking->update(['status' => 'cancelled']);
            $booking->ticketTier()->decrement('booked_count', $booking->quantity);
        });

        return back()->with('status', __('Ticket reservation cancelled successfully.'));
    }
}
