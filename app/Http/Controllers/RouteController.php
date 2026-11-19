<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Venue;
use App\Services\RouteService;
use App\Support\TimeBucket;
use Illuminate\Http\Request;

class RouteController extends Controller
{
    public function index(Request $request)
    {
        $toLat = $request->input('to_lat', $request->input('dest_lat'));
        $toLng = $request->input('to_lng', $request->input('dest_lng'));
        $venueName = $request->input('venue_name', $request->input('dest_name'));
        $eventId = $request->input('event_id');
        $eventSlug = $request->input('event_slug');

        $event = null;
        if ($eventId) {
            $event = Event::with('venue')->find($eventId);
        } elseif ($eventSlug) {
            $event = Event::with('venue')->where('slug', $eventSlug)->first();
        }

        if ($event) {
            $toLat = $toLat ?: $event->lat;
            $toLng = $toLng ?: $event->lng;
            $venueName = $venueName ?: ($event->venue ? $event->venue->name : $event->title);
        }

        $prefill = array_filter([
            'from_lat' => $request->input('from_lat'),
            'from_lng' => $request->input('from_lng'),
            'to_lat' => $toLat,
            'to_lng' => $toLng,
            'venue_name' => $venueName,
            'event_id' => $event?->id ?? $eventId,
            'event_title' => $event?->title,
            'event_slug' => $event?->slug,
        ], fn ($v) => $v !== null && $v !== '');

        return view('route.index', [
            'venues' => Venue::approved()->where('is_restricted', false)->orderBy('name')->get(['id', 'name', 'lat', 'lng']),
            'prefill' => $prefill,
            'buckets' => TimeBucket::labels(),
            'bucket' => TimeBucket::current(),
            'event' => $event,
        ]);
    }

    public function compute(Request $request, RouteService $svc)
    {
        $d = $request->validate([
            'from_lat' => ['required', 'numeric', 'between:20,27'],
            'from_lng' => ['required', 'numeric', 'between:88,93'],
            'to_lat' => ['required', 'numeric', 'between:20,27'],
            'to_lng' => ['required', 'numeric', 'between:88,93'],
            'bucket' => ['nullable', 'in:day,evening,night,late'],
            'mode' => ['nullable', 'in:driving,walking'],
        ]);
        try {
            return response()->json($svc->compute(
                (float) $d['from_lat'], (float) $d['from_lng'], (float) $d['to_lat'], (float) $d['to_lng'],
                TimeBucket::normalize($d['bucket'] ?? null),
                $d['mode'] ?? 'driving'
            ));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        }
    }
}
