<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use App\Services\RouteService;
use App\Support\TimeBucket;
use Illuminate\Http\Request;

class RouteController extends Controller
{
    public function index(Request $request)
    {
        return view('route.index', [
            'venues' => Venue::approved()->where('is_restricted', false)->orderBy('name')->get(['id', 'name', 'lat', 'lng']),
            'prefill' => $request->only(['from_lat', 'from_lng', 'to_lat', 'to_lng', 'venue_name']),
            'buckets' => TimeBucket::labels(),
            'bucket' => TimeBucket::current(),
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
        ]);
        try {
            return response()->json($svc->compute(
                (float) $d['from_lat'], (float) $d['from_lng'], (float) $d['to_lat'], (float) $d['to_lng'],
                TimeBucket::normalize($d['bucket'] ?? null)
            ));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        }
    }
}
