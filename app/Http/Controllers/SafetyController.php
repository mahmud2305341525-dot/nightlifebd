<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\SafeHaven;
use App\Services\AlertService;
use App\Support\Geo;
use Illuminate\Http\Request;

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

    public function sosPage()
    {
        return view('sos.index');
    }

    public function emergencyData(Request $request)
    {
        $u = $request->user();

        return response()->json([
            'user' => $u->name,
            'contacts' => $u->emergencyContacts->map->only(['name', 'phone', 'relation'])->values(),
            'havens' => SafeHaven::limit(400)->get(['type', 'name', 'lat', 'lng', 'phone']),
        ]);
    }

    public function sos(Request $request, AlertService $alerts)
    {
        $d = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);
        $user = $request->user()->load('emergencyContacts');
        $where = isset($d['lat'], $d['lng']) ? "https://maps.google.com/?q={$d['lat']},{$d['lng']}" : 'location unavailable';
        $msg = "SOS! {$user->name} needs help. Location: {$where}. / জরুরি! {$user->name} সাহায্য চাইছেন। অবস্থান: {$where}";

        $sent = $alerts->notifyContacts($user, 'NightLifeBD SOS', $msg);
        AuditLog::record($user->id, 'sos.triggered', ['lat' => $d['lat'] ?? null, 'lng' => $d['lng'] ?? null] + $sent);

        return response()->json(['ok' => true] + $sent);
    }
}
