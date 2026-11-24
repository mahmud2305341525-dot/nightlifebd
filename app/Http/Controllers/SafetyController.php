<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\SafeHaven;
use App\Services\AlertService;
use App\Support\Geo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SafetyController extends Controller
{
    public function nearMe()
    {
        return view('near-me');
    }

    /** Nearest help points with distance and an ETA estimated at Dhaka traffic speed. */
    public function nearest(Request $request)
    {
        $d = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'type' => ['nullable', 'in:police,hospital,pharmacy'],
        ]);
        $speed = (float) config('nightlife.avg_speed_kmh', 20);

        $items = SafeHaven::query()->when($d['type'] ?? null, fn ($q, $t) => $q->where('type', $t))->get()
            ->map(function (SafeHaven $h) use ($d, $speed) {
                $m = Geo::distance((float) $d['lat'], (float) $d['lng'], $h->lat, $h->lng);

                return [
                    'type' => $h->type, 'name' => $h->name, 'phone' => $h->phone, 'lat' => $h->lat, 'lng' => $h->lng,
                    'distance_m' => (int) round($m),
                    'eta_min' => max(1, (int) ceil(($m / 1000) / $speed * 60)),
                ];
            })
            ->sortBy('distance_m')->take(10)->values();

        return response()->json($items);
    }

    public function sosPage(Request $request)
    {
        $contacts = $request->user()?->emergencyContacts()->get() ?? collect();

        return view('sos.index', compact('contacts'));
    }

    public function emergencyData(Request $request)
    {
        $u = $request->user();
        $data = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'limit' => ['nullable', 'integer', 'between:1,100'],
        ]);
        $havens = SafeHaven::query()->get(['type', 'name', 'lat', 'lng', 'phone']);
        if (isset($data['lat'], $data['lng'])) {
            $havens = $havens->map(function ($h) use ($data) {
                $h->setAttribute('_distance_m', (int) round(Geo::distance((float) $data['lat'], (float) $data['lng'], $h->lat, $h->lng)));

                return $h;
            })->sortBy('_distance_m');
        }
        $havens = $havens->take((int) ($data['limit'] ?? 50))->values();

        return response()->json([
            'user' => $u->name,
            'contacts' => $u->emergencyContacts->map->only(['name', 'phone', 'relation'])->values(),
            'havens' => $havens,
        ]);
    }

    public function sos(Request $request, AlertService $alerts)
    {
        $d = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'request_id' => ['nullable', 'string', 'max:64'],
        ]);
        $user = $request->user()->load('emergencyContacts');

        // Idempotency: suppress accidental duplicate requests within a 60-second window
        if (! empty($d['request_id'])) {
            $cacheKey = 'sos_req_'.$user->id.'_'.$d['request_id'];
            if (Cache::has($cacheKey)) {
                return response()->json([
                    'ok' => true,
                    'is_duplicate' => true,
                    'summary' => __('Duplicate alert suppressed (already requested in the last 60 seconds).'),
                    'emergency_disclaimer' => __('Emergency services (999) are not automatically contacted. Call 999 directly for police/ambulance.'),
                    'contacts' => $user->emergencyContacts->count(),
                    'email' => 0,
                    'telegram' => 0,
                ]);
            }
            Cache::put($cacheKey, true, now()->addSeconds(60));
        }

        $where = isset($d['lat'], $d['lng']) ? "https://maps.google.com/?q={$d['lat']},{$d['lng']}" : 'location unavailable';
        $msg = "SOS! {$user->name} needs help. Location: {$where}. / জরুরি! {$user->name} সাহায্য চাইছেন। অবস্থান: {$where}";

        $result = $alerts->notifyContacts($user, 'NightLifeBD SOS', $msg);

        AuditLog::record($user->id, 'sos.triggered', [
            'lat' => $d['lat'] ?? null,
            'lng' => $d['lng'] ?? null,
            'contacts' => $result['contacts_count'],
            'email' => $result['email'],
            'telegram' => $result['telegram'],
            'is_simulation' => $result['is_simulation'],
        ]);

        return response()->json([
            'ok' => true,
            'is_duplicate' => false,
            'delivery' => $result['delivery'],
            'summary' => $result['summary'],
            'is_simulation' => $result['is_simulation'],
            'emergency_disclaimer' => __('Emergency services (999) are not automatically contacted. Call 999 directly for police/ambulance.'),
            'contacts' => $result['contacts'],
            'email' => $result['email'],
            'telegram' => $result['telegram'],
        ]);
    }
}
