<?php

namespace App\Http\Controllers;

use App\Models\DarkSpot;
use Illuminate\Http\Request;

class DarkSpotController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:20,27'],
            'lng' => ['required', 'numeric', 'between:88,93'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);
        $spot = DarkSpot::create([
            'user_id' => $request->user()->id,
            'lat' => $data['lat'],
            'lng' => $data['lng'],
            'note' => isset($data['note']) ? strip_tags($data['note']) : null,
            'status' => 'community',
        ]);

        $nearbyCount = DarkSpot::whereBetween('lat', [$spot->lat - 0.00045, $spot->lat + 0.00045])
            ->whereBetween('lng', [$spot->lng - 0.00045, $spot->lng + 0.00045])
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
        if ($nearbyCount >= 3) {
            DarkSpot::whereBetween('lat', [$spot->lat - 0.00045, $spot->lat + 0.00045])
                ->whereBetween('lng', [$spot->lng - 0.00045, $spot->lng + 0.00045])
                ->update(['status' => 'pending_moderator']);
        }

        return response()->json(['ok' => true]);
    }
}
