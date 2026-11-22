<?php

namespace App\Http\Controllers;

use App\Models\DarkSpot;
use App\Models\Report;
use App\Support\TimeBucket;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HeatmapController extends Controller
{
    public function index()
    {
        return view('heatmap.index', ['bucket' => TimeBucket::current()]);
    }

    /** [[lat, lng, intensity 0-1], ...] for Leaflet.heat */
    public function data(Request $request)
    {
        $hour = $request->has('hour') ? (int) $request->input('hour') : null;
        $bucket = $hour !== null ? TimeBucket::fromHour($hour) : TimeBucket::normalize($request->input('bucket'));
        $bounds = $request->validate([
            'north' => ['nullable', 'numeric', 'between:-90,90'],
            'south' => ['nullable', 'numeric', 'between:-90,90'],
            'east' => ['nullable', 'numeric', 'between:-180,180'],
            'west' => ['nullable', 'numeric', 'between:-180,180'],
            'since' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'between:1,1000'],
        ]);
        $limit = (int) ($bounds['limit'] ?? 500);
        $points = [];
        $incidents = [];

        $reportQuery = Report::verified()->whereNull('duplicate_of')->where('category', '!=', 'positive')
            ->with(['user:id,trust_score', 'venue:id,lat,lng']);
        if (isset($bounds['since'])) {
            $reportQuery->where('incident_time', '>=', now()->parse($bounds['since']));
        }
        if (isset($bounds['north'], $bounds['south'], $bounds['east'], $bounds['west'])) {
            $reportQuery->where(function ($query) use ($bounds) {
                $query->whereBetween('lat', [(float) $bounds['south'], (float) $bounds['north']])
                    ->whereBetween('lng', [(float) $bounds['west'], (float) $bounds['east']])
                    ->orWhereNotNull('venue_id');
            });
        }
        $reportQuery->limit($limit)
            ->get()
            ->each(function (Report $r) use ($bucket, &$points, &$incidents) {
                $c = $r->coords();
                if (! $c) {
                    return;
                }
                $rHour = (int) $r->incident_time->format('G');
                $baseW = ((int) $r->severity / 5) * (float) ($r->user->trust_score ?? 0.5);

                $incidents[] = [
                    'lat' => round($c[0], 5),
                    'lng' => round($c[1], 5),
                    'hour' => $rHour,
                    'weight' => round($baseW, 3),
                    'category' => $r->category,
                ];

                $w = $baseW * ($r->bucket() === $bucket ? 1.0 : 0.25) * 1.6;
                $points[] = [round($c[0], 5), round($c[1], 5), round(min(1, $w), 2)];
            });

        $darkSpots = [];
        $darkQuery = DarkSpot::query();
        if (isset($bounds['north'], $bounds['south'], $bounds['east'], $bounds['west'])) {
            $darkQuery->whereBetween('lat', [(float) $bounds['south'], (float) $bounds['north']])
                ->whereBetween('lng', [(float) $bounds['west'], (float) $bounds['east']]);
        }
        $darkQuery->limit($limit)->get(['lat', 'lng'])->each(function ($d) use (&$points, &$darkSpots) {
            $darkSpots[] = ['lat' => round($d->lat, 5), 'lng' => round($d->lng, 5)];
            $points[] = [round($d->lat, 5), round($d->lng, 5), 0.5];
        });

        return response()->json([
            'bucket' => $bucket,
            'hour' => $hour,
            'points' => $points,
            'incidents' => $incidents,
            'dark_spots' => $darkSpots,
        ]);
    }

    /** Live incident feed — last 2 hours of verified reports for the dashboard. */
    public function feed(Request $request)
    {
        $data = $request->validate([
            'north' => ['nullable', 'numeric', 'between:-90,90'],
            'south' => ['nullable', 'numeric', 'between:-90,90'],
            'east' => ['nullable', 'numeric', 'between:-180,180'],
            'west' => ['nullable', 'numeric', 'between:-180,180'],
            'hours' => ['nullable', 'integer', 'between:1,168'],
            'limit' => ['nullable', 'integer', 'between:1,100'],
        ]);
        $since = now()->subHours((int) ($data['hours'] ?? 2));

        $reports = Report::verified()
            ->whereNull('duplicate_of')
            ->where('category', '!=', 'positive')
            ->where('incident_time', '>=', $since)
            ->with(['venue:id,name,area', 'user:id,trust_score'])
            ->when(isset($data['north'], $data['south'], $data['east'], $data['west']), function ($query) use ($data) {
                $query->where(function ($where) use ($data) {
                    $where->whereBetween('lat', [(float) $data['south'], (float) $data['north']])
                        ->whereBetween('lng', [(float) $data['west'], (float) $data['east']])
                        ->orWhereNotNull('venue_id');
                });
            })
            ->orderByDesc('incident_time')
            ->limit((int) ($data['limit'] ?? 20))
            ->get();

        $categoryLabels = [
            'theft' => 'Theft / Robbery',
            'harassment' => 'Harassment',
            'fighting' => 'Fighting / Violence',
            'poor_lighting' => 'Poor Lighting',
            'other' => 'Other Incident',
        ];

        $data = $reports->map(fn (Report $r) => [
            'id' => $r->id,
            'category' => $r->category,
            'category_label' => $categoryLabels[$r->category] ?? ucfirst(str_replace('_', ' ', $r->category)),
            'severity' => $r->severity,
            'body' => Str::limit($r->body, 120),
            'area' => $r->venue?->area ?? null,
            'venue_name' => $r->venue?->name ?? null,
            'time_ago' => $r->incident_time->diffForHumans(),
        ]);

        return response()->json($data);
    }
}
