<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Trip;
use App\Services\AlertService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'share_pin' => ['nullable', 'regex:/^\d{4,8}$/'],
            'max_views' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $maxActive = (int) config('nightlife.max_active_trips', 1);

        return DB::transaction(function () use ($request, $d, $alerts, $maxActive) {
            $activeCount = $request->user()->trips()
                ->whereNull('ended_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->count();

            if ($activeCount >= $maxActive) {
                return back()->withInput()->withErrors([
                    'destination' => __('You already have an active live trip in progress. Please complete or end your current trip before starting a new one.'),
                ]);
            }

            $trip = $request->user()->trips()->create([
                'token' => Str::random(40),
                'kind' => $d['kind'],
                'destination' => $d['destination'] ?? null,
                'vehicle_plate' => isset($d['vehicle_plate']) ? strtoupper($d['vehicle_plate']) : null,
                'expires_at' => now()->addMinutes((int) $d['duration']),
                'share_pin' => $d['share_pin'] ?? null,
                'max_views' => $d['max_views'] ?? null,
            ]);

            $link = route('track.show', $trip->token);
            $msg = "{$request->user()->name} started a ".($trip->kind === 'ride' ? 'ride' : 'trip')
                .($trip->vehicle_plate ? " in vehicle {$trip->vehicle_plate}" : '')
                .($trip->destination ? " to {$trip->destination}" : '')
                .". Follow live: {$link} (link expires {$trip->expires_at->format('h:i A')}).";
            $sent = $alerts->notifyContacts($request->user()->load('emergencyContacts'), 'NightLifeBD live trip', $msg);
            AuditLog::record($request->user()->id, 'trip.started', ['trip_id' => $trip->id] + $sent);

            return redirect()->route('trips.show', $trip->token)->with('status', __('Trip started. Keep this page open so your location keeps updating.'));
        });
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

    public function track(Request $request, Trip $trip)
    {
        if (! $trip->isActive()) {
            return response()->view('trips.expired', [], 410);
        }
        if ($trip->share_pin && ! hash_equals((string) $trip->share_pin, (string) $request->session()->get('trip_pin_'.$trip->id))) {
            return response()->view('trips.pin', ['trip' => $trip], 200);
        }
        $sessionKey = 'trip_viewed_'.$trip->id;
        if (! $request->session()->get($sessionKey)) {
            if ($trip->max_views) {
                $updated = Trip::where('id', $trip->id)
                    ->where('view_count', '<', $trip->max_views)
                    ->increment('view_count');
                if ($updated === 0) {
                    return response()->view('trips.expired', [], 410);
                }
            } else {
                $trip->increment('view_count');
            }
            $request->session()->put($sessionKey, true);
        }

        return view('trips.track', ['trip' => $trip->load('user:id,name')]);
    }

    public function verifyPin(Request $request, Trip $trip)
    {
        if (! $trip->isActive()) {
            return response()->view('trips.expired', [], 410);
        }
        $data = $request->validate(['pin' => ['required', 'string', 'max:8']]);
        if (! $trip->share_pin || hash_equals((string) $trip->share_pin, (string) $data['pin'])) {
            $request->session()->put('trip_pin_'.$trip->id, (string) $data['pin']);

            return redirect()->route('track.show', $trip->token);
        }

        return back()->withErrors(['pin' => __('Incorrect tracking PIN.')]);
    }

    public function trackData(Request $request, Trip $trip)
    {
        if (! $trip->isActive()) {
            return response()->json([
                'active' => false,
                'message' => __('This trip has ended or the sharing link has expired.'),
            ], 410);
        }
        if ($trip->share_pin && ! hash_equals((string) $trip->share_pin, (string) $request->session()->get('trip_pin_'.$trip->id))) {
            return response()->json(['active' => false, 'message' => __('PIN required.')], 403);
        }
        $sessionKey = 'trip_viewed_'.$trip->id;
        if (! $request->session()->get($sessionKey)) {
            if ($trip->max_views) {
                $updated = Trip::where('id', $trip->id)
                    ->where('view_count', '<', $trip->max_views)
                    ->increment('view_count');
                if ($updated === 0) {
                    return response()->json([
                        'active' => false,
                        'message' => __('This tracking link has reached its view limit and expired.'),
                    ], 410);
                }
            } else {
                $trip->increment('view_count');
            }
            $request->session()->put($sessionKey, true);
        }

        $secondsSincePing = $trip->last_ping_at ? (int) abs(now()->diffInSeconds($trip->last_ping_at)) : null;
        $isStale = ($secondsSincePing !== null && $secondsSincePing > 120);

        return response()->json([
            'active' => true,
            'lat' => $trip->last_lat,
            'lng' => $trip->last_lng,
            'last_ping_at' => $trip->last_ping_at?->diffForHumans(),
            'last_ping_seconds' => $secondsSincePing,
            'is_stale' => $isStale,
            'expires_at' => $trip->expires_at->format('h:i A'),
            'path' => $trip->pings()->latest('id')->limit(60)->get(['lat', 'lng'])->reverse()->values()->map(fn ($p) => [$p->lat, $p->lng]),
        ]);
    }
}
