<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventBooking;
use App\Models\Venue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class EventModerationController extends Controller
{
    public function approve(Request $request, Event $event): RedirectResponse
    {
        $event->update(['status' => 'published', 'submission_status' => 'approved']);
        AuditLog::record($request->user()->id, 'event.approved', ['event_id' => $event->id]);

        return back()->with('status', __('Event approved and published.'));
    }

    public function reject(Request $request, Event $event): RedirectResponse
    {
        $event->update(['status' => 'cancelled', 'submission_status' => 'rejected']);
        AuditLog::record($request->user()->id, 'event.rejected', ['event_id' => $event->id]);

        return back()->with('status', __('Event submission rejected.'));
    }

    public function index(): View
    {
        $events = Event::with(['venue', 'organizer', 'ticketTiers'])
            ->withCount('bookings')
            ->latest('starts_at')
            ->paginate(15);

        return view('admin.events.index', [
            'events' => $events,
        ]);
    }

    public function create(): View
    {
        $venues = Venue::orderBy('name')->get(['id', 'name', 'area', 'lat', 'lng']);
        $categories = Event::CATEGORIES;

        return view('admin.events.create', [
            'venues' => $venues,
            'categories' => $categories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'category' => ['required', 'string', 'in:'.implode(',', array_keys(Event::CATEGORIES))],
            'venue_id' => ['nullable', 'exists:venues,id'],
            'area' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'description' => ['required', 'string', 'max:5000'],
            'is_featured' => ['nullable', 'boolean'],
            'tiers' => ['required', 'array', 'min:1'],
            'tiers.*.name' => ['required', 'string', 'max:100'],
            'tiers.*.price' => ['required', 'numeric', 'min:0'],
            'tiers.*.capacity' => ['required', 'integer', 'min:1'],
            'tiers.*.max_per_user' => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        $venue = ! empty($validated['venue_id']) ? Venue::find($validated['venue_id']) : null;

        $event = Event::create([
            'title' => $validated['title'],
            'slug' => Str::slug($validated['title']).'-'.Str::random(5),
            'category' => $validated['category'],
            'venue_id' => $validated['venue_id'] ?? null,
            'organizer_id' => auth()->id(),
            'area' => $validated['area'],
            'address' => $validated['address'],
            'lat' => $venue?->lat,
            'lng' => $venue?->lng,
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'],
            'description' => $validated['description'],
            'is_featured' => (bool) ($validated['is_featured'] ?? false),
            'status' => 'published',
        ]);

        foreach ($validated['tiers'] as $tierData) {
            $event->ticketTiers()->create([
                'name' => $tierData['name'],
                'price' => (float) $tierData['price'],
                'capacity' => (int) $tierData['capacity'],
                'max_per_user' => (int) $tierData['max_per_user'],
            ]);
        }

        return redirect()
            ->route('admin.events.index')
            ->with('status', __('Concert/Event published successfully!'));
    }

    public function scanner(): View
    {
        return view('admin.events.scanner');
    }

    public function verifyCheckIn(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $code = trim($request->input('code'));

        $booking = EventBooking::where('qr_token', $code)
            ->orWhere('booking_reference', $code)
            ->with(['event', 'ticketTier', 'user'])
            ->first();

        if (! $booking) {
            return response()->json([
                'success' => false,
                'message' => __('Invalid ticket! No booking found with this QR or code.'),
            ], 404);
        }
        if (in_array($booking->payment_status, ['pending', 'expired', 'refunded', 'cancelled'], true)) {
            return response()->json([
                'success' => false,
                'message' => __('Payment not confirmed or ticket refunded. Entry denied.'),
            ], 422);
        }

        if ($booking->isCancelled()) {
            return response()->json([
                'success' => false,
                'message' => __('Ticket is CANCELLED! Entry denied.'),
                'booking' => [
                    'reference' => $booking->booking_reference,
                    'status' => 'cancelled',
                ],
            ], 422);
        }

        if ($booking->isCheckedIn()) {
            return response()->json([
                'success' => false,
                'message' => __('Already Checked In at :time by :officer', [
                    'time' => $booking->checked_in_at?->format('h:i A, d M Y'),
                    'officer' => $booking->checkedInByUser?->name ?? 'Staff',
                ]),
                'booking' => [
                    'reference' => $booking->booking_reference,
                    'status' => 'checked_in',
                    'checked_in_at' => $booking->checked_in_at?->toIso8601String(),
                ],
            ], 409);
        }

        $booking->markCheckedIn(auth()->user());

        return response()->json([
            'success' => true,
            'message' => __('Access Granted! Welcome :name to :event', [
                'name' => $booking->user->name,
                'event' => $booking->event->title,
            ]),
            'booking' => [
                'reference' => $booking->booking_reference,
                'attendee_name' => $booking->user->name,
                'event_title' => $booking->event->title,
                'tier_name' => $booking->ticketTier->name,
                'quantity' => $booking->quantity,
                'checked_in_at' => $booking->checked_in_at?->format('h:i A, d M Y'),
            ],
        ]);
    }
}
