<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Trip;
use App\Services\AlertService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TripController extends Controller
{
    public function index(Request $request)
    {
        return view('trips.index', [
            'trips' => $request->user()->trips()->latest()->limit(10)->get(),
            'hasContacts' => $request->user()->emergencyContacts()->exists(),
        ]);
    }

    public function store(Request $request, AlertService $alerts)
    {
        $d = $request->validate([
            'kind' => ['required', 'in:trip,ride'],
            'duration' => ['required', 'integer', 'between:15,180'],
            'destination' => ['nullable', 'string', 'max:120'],
            'vehicle_plate' => ['nullable', 'string', 'max:30'],
        ]);
        $trip = $request->user()->trips()->create([
            'token' => Str::random(40),
            'kind' => $d['kind'],
            'destination' => $d['destination'] ?? null,
            'vehicle_plate' => isset($d['vehicle_plate']) ? strtoupper($d['vehicle_plate']) : null,
            'expires_at' => now()->addMinutes((int) $d['duration']),
        ]);

        $link = route('track.show', $trip->token);
        $msg = "{$request->user()->name} started a ".($trip->kind === 'ride' ? 'ride' : 'trip')
            .($trip->vehicle_plate ? " in vehicle {$trip->vehicle_plate}" : '')
            .($trip->destination ? " to {$trip->destination}" : '')
            .". Follow live: {$link} (link expires {$trip->expires_at->format('h:i A')}).";
        $sent = $alerts->notifyContacts($request->user()->load('emergencyContacts'), 'NightLifeBD live trip', $msg);
        AuditLog::record($request->user()->id, 'trip.started', ['trip_id' => $trip->id] + $sent);

        return redirect()->route('trips.show', $trip->token)->with('status', __('Trip started. Keep this page open so your location keeps updating.'));
    }

    public function show(Request $request, Trip $trip)
    {
        abort_unless($trip->user_id === $request->user()->id, 403);

        return view('trips.show', ['trip' => $trip, 'link' => route('track.show', $trip->token)]);
    }

    public function ping(Request $request, Trip $trip)
    {
        abort_unless($trip->user_id === $request->user()->id, 403);
        abort_unless($trip->isActive(), 410, 'Trip is no longer active.');
        $d = $request->validate(['lat' => ['required', 'numeric', 'between:-90,90'], 'lng' => ['required', 'numeric', 'between:-180,180']]);

        $trip->pings()->create(['lat' => $d['lat'], 'lng' => $d['lng'], 'created_at' => now()]);
        $trip->update(['last_lat' => $d['lat'], 'last_lng' => $d['lng'], 'last_ping_at' => now()]);

        return response()->json(['ok' => true, 'expires_at' => $trip->expires_at->toIso8601String()]);
    }

    public function end(Request $request, Trip $trip, AlertService $alerts)
    {
        abort_unless($trip->user_id === $request->user()->id, 403);
        $trip->update(['ended_at' => now()]);
        $alerts->notifyContacts($request->user()->load('emergencyContacts'), 'NightLifeBD trip ended', "{$request->user()->name} ended the trip and is safe.");

        return redirect()->route('trips.index')->with('status', __('Trip ended. Your contacts were told you are safe.'));
    }

    // ---- public tracking page (anyone with the secret 40-character link) ----

    public function track(Trip $trip)
    {
        if (! $trip->isActive()) {
            return response()->view('trips.expired', [], 410);
        }

        return view('trips.track', ['trip' => $trip->load('user:id,name')]);
    }

    public function trackData(Trip $trip)
    {
        if (! $trip->isActive()) {
            return response()->json(['active' => false], 410);
        }

        return response()->json([
            'active' => true,
            'lat' => $trip->last_lat,
            'lng' => $trip->last_lng,
            'last_ping_at' => $trip->last_ping_at?->diffForHumans(),
            'expires_at' => $trip->expires_at->format('h:i A'),
            'path' => $trip->pings()->latest('id')->limit(60)->get(['lat', 'lng'])->reverse()->values()->map(fn ($p) => [$p->lat, $p->lng]),
        ]);
    }
}
