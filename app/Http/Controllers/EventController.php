<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Support\TimeBucket;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventController extends Controller
{
    public function index(Request $request): View
    {
        $query = Event::query()
            ->with(['venue.scores', 'ticketTiers'])
            ->published()
            ->upcoming()
            ->orderBy('starts_at', 'asc');

        if ($cat = $request->input('category')) {
            $query->where('category', $cat);
        }

        if ($area = $request->input('area')) {
            $query->where('area', $area);
        }

        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        $events = $query->paginate(12)->withQueryString();

        $areas = Event::published()
            ->upcoming()
            ->distinct()
            ->pluck('area')
            ->sort()
            ->values();

        $categories = Event::CATEGORIES;

        return view('events.index', [
            'events' => $events,
            'areas' => $areas,
            'categories' => $categories,
            'selectedCategory' => $request->input('category'),
            'selectedArea' => $request->input('area'),
            'search' => $request->input('q'),
        ]);
    }

    public function show(Event $event): View
    {
        abort_unless($event->status === 'published' || (auth()->check() && (auth()->user()->isStaff() || auth()->id() === $event->organizer_id)), 404);

        $event->load(['venue.scores', 'ticketTiers', 'organizer']);

        $userBooking = auth()->check()
            ? auth()->user()->eventBookings()->where('event_id', $event->id)->where('status', '!=', 'cancelled')->first()
            : null;

        $bucket = TimeBucket::current();
        $safetyScore = $event->venue ? $event->venue->scoreFor($bucket) : null;

        return view('events.show', [
            'event' => $event,
            'userBooking' => $userBooking,
            'safetyScore' => $safetyScore,
            'bucket' => $bucket,
        ]);
    }
}
