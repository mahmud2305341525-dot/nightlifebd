<?php

namespace App\Http\Controllers;

use App\Models\DarkSpot;
use App\Models\Report;
use App\Support\TimeBucket;
use Illuminate\Http\Request;

class HeatmapController extends Controller
{
    public function index()
    {
        return view('heatmap.index', ['bucket' => TimeBucket::current()]);
    }

    /** [[lat, lng, intensity 0-1], ...] for Leaflet.heat */
    public function data(Request $request)
    {
        $bucket = TimeBucket::normalize($request->input('bucket'));
        $points = [];

        Report::verified()->whereNull('duplicate_of')->where('category', '!=', 'positive')
            ->with(['user:id,trust_score', 'venue:id,lat,lng'])->get()
            ->each(function (Report $r) use ($bucket, &$points) {
                $c = $r->coords();
                if (! $c) {
                    return;
                }
                $w = ((int) $r->severity / 5) * (float) ($r->user->trust_score ?? 0.5) * ($r->bucket() === $bucket ? 1.0 : 0.25) * 1.6;
                $points[] = [round($c[0], 5), round($c[1], 5), round(min(1, $w), 2)];
            });

        DarkSpot::all(['lat', 'lng'])->each(function ($d) use (&$points) {
            $points[] = [round($d->lat, 5), round($d->lng, 5), 0.5];
        });

        return response()->json(['bucket' => $bucket, 'points' => $points]);
    }
}
