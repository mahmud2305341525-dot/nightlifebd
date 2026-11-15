<?php

namespace App\Services;

use App\Models\DarkSpot;
use App\Models\Report;
use App\Models\SafeHaven;
use App\Support\Geo;
use App\Support\TimeBucket;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * "Safest route" without a routing engine of our own:
 *  1. ask a free routing API (OpenRouteService with a key, otherwise the public OSRM demo server) for alternatives,
 *  2. sample a point every ~50 m along each alternative,
 *  3. score every route against OUR data (verified incidents, community dark spots, safe havens),
 *  4. recommend the safest route that is not more than 30% longer than the fastest.
 */
class RouteService
{
    public const INCIDENT_RADIUS = 100;  // metres
    public const HAVEN_RADIUS = 150;     // metres
    public const DARK_SPOT_RISK = 2.0;
    public const HAVEN_BONUS = 1.0;

    /**
     * @return array{routes:array,fastest:int,safest:int,summary:string,source:string}
     *
     * @throws \RuntimeException when no routing service is reachable
     */
    public function compute(float $fromLat, float $fromLng, float $toLat, float $toLng, string $bucket): array
    {
        [$raw, $source] = $this->fetchAlternatives($fromLat, $fromLng, $toLat, $toLng);

        $all = [];
        foreach ($raw as $r) {
            foreach ($r['coords'] as $c) {
                $all[] = $c;
            }
        }
        $data = $this->riskData(Geo::bbox($all), $bucket);

        $routes = [];
        foreach ($raw as $i => $r) {
            $score = $this->scoreRoute($r['coords'], $data['incidents'], $data['dark_spots'], $data['havens']);
            $km = max(0.05, $r['distance'] / 1000);
            $routes[] = [
                'id' => $i,
                'distance_m' => (int) round($r['distance']),
                'duration_s' => (int) round($r['duration']),
                'risk' => round($score['risk'], 2),
                'risk_per_km' => round(max(0, $score['risk']) / $km, 2),
                'incidents' => count($score['incident_ids']),
                'dark_spots' => $score['dark_spots'],
                'havens' => $score['havens'],
                'haven_list' => $score['haven_list'],
                'coords' => array_map(fn ($c) => [round($c[1], 5), round($c[0], 5)], $r['coords']), // [lat,lng] for Leaflet
            ];
        }

        [$fastest, $safest] = $this->choose($routes);

        return [
            'routes' => $routes,
            'fastest' => $fastest,
            'safest' => $safest,
            'summary' => $this->summary($routes[$fastest], $routes[$safest], $fastest === $safest),
            'source' => $source,
        ];
    }

    /**
     * Pure scoring function (no DB, easy to unit test).
     * $coords = [[lng,lat],...]; incidents = [['id','lat','lng','weight']]; dark spots/havens = [['lat','lng', ...]]
     *
     * @return array{risk:float,incident_ids:array,dark_spots:int,havens:int,haven_list:array}
     */
    public function scoreRoute(array $coords, array $incidents, array $darkSpots, array $havens): array
    {
        $pts = Geo::sample($coords, 50);
        $risk = 0.0;
        $ids = [];

        foreach ($incidents as $inc) {
            if (Geo::minDistance($inc['lat'], $inc['lng'], $pts) <= self::INCIDENT_RADIUS) {
                $risk += $inc['weight'];
                $ids[] = $inc['id'];
            }
        }
        $dark = 0;
        foreach ($darkSpots as $d) {
            if (Geo::minDistance($d['lat'], $d['lng'], $pts) <= self::INCIDENT_RADIUS) {
                $risk += self::DARK_SPOT_RISK;
                $dark++;
            }
        }
        $haveList = [];
        foreach ($havens as $h) {
            if (Geo::minDistance($h['lat'], $h['lng'], $pts) <= self::HAVEN_RADIUS) {
                $risk -= self::HAVEN_BONUS;
                $haveList[] = ['type' => $h['type'] ?? 'police', 'name' => $h['name'] ?? '', 'lat' => $h['lat'], 'lng' => $h['lng']];
            }
        }

        return ['risk' => $risk, 'incident_ids' => $ids, 'dark_spots' => $dark, 'havens' => count($haveList), 'haven_list' => $haveList];
    }

    /** @return array{0:int,1:int} indexes of [fastest, safest] */
    public function choose(array $routes): array
    {
        $fastest = 0;
        foreach ($routes as $i => $r) {
            if ($r['duration_s'] < $routes[$fastest]['duration_s']) {
                $fastest = $i;
            }
        }
        $limit = $routes[$fastest]['distance_m'] * config('nightlife.max_detour', 1.3);
        $safest = $fastest;
        foreach ($routes as $i => $r) {
            if ($r['distance_m'] > $limit) {
                continue;
            }
            $best = $routes[$safest];
            if ($r['risk_per_km'] < $best['risk_per_km'] - 0.001
                || (abs($r['risk_per_km'] - $best['risk_per_km']) <= 0.001 && $r['duration_s'] < $best['duration_s'])) {
                $safest = $i;
            }
        }

        return [$fastest, $safest];
    }

    private function summary(array $fast, array $safe, bool $same): string
    {
        if ($same) {
            return __('The fastest route is also the safest option we found (:n incident areas nearby).', ['n' => $fast['incidents']]);
        }
        $extraMin = max(0, (int) round(($safe['duration_s'] - $fast['duration_s']) / 60));
        $avoided = max(0, $fast['incidents'] - $safe['incidents']);

        return __('Safest route is :min min longer, avoids :avoided incident area(s) and passes :havens help point(s).', [
            'min' => $extraMin, 'avoided' => $avoided, 'havens' => $safe['havens'],
        ]);
    }

    /** Verified, de-duplicated incidents + dark spots + havens inside a bounding box. */
    public function riskData(array $bbox, string $bucket): array
    {
        [$minLat, $maxLat, $minLng, $maxLng] = $bbox;

        $reports = Report::query()
            ->verified()
            ->whereNull('duplicate_of')
            ->where(fn ($q) => $q->where('category', '!=', 'positive')->orWhereNull('category'))
            ->with(['user:id,trust_score', 'venue:id,lat,lng'])
            ->withCount('duplicates')
            ->get();

        $incidents = [];
        foreach ($reports as $r) {
            $c = $r->coords();
            if (! $c || $c[0] < $minLat || $c[0] > $maxLat || $c[1] < $minLng || $c[1] > $maxLng) {
                continue;
            }
            $ageDays = max(0, (now()->timestamp - $r->incident_time->timestamp) / 86400);
            $weight = ((int) $r->severity ?: 2)
                * (float) ($r->user->trust_score ?? 0.5)
                * exp(-$ageDays / 30)
                * ($r->bucket() === $bucket ? 1.0 : 0.3)
                * (1 + 0.25 * min(3, $r->duplicates_count));
            $incidents[] = ['id' => $r->id, 'lat' => $c[0], 'lng' => $c[1], 'weight' => $weight];
        }

        $dark = DarkSpot::whereBetween('lat', [$minLat, $maxLat])->whereBetween('lng', [$minLng, $maxLng])
            ->get(['lat', 'lng'])->map(fn ($d) => ['lat' => $d->lat, 'lng' => $d->lng])->all();

        $havens = SafeHaven::whereBetween('lat', [$minLat, $maxLat])->whereBetween('lng', [$minLng, $maxLng])
            ->get(['type', 'name', 'lat', 'lng'])->map(fn ($h) => ['type' => $h->type, 'name' => $h->name, 'lat' => $h->lat, 'lng' => $h->lng])->all();

        return ['incidents' => $incidents, 'dark_spots' => $dark, 'havens' => $havens];
    }

    /** @return array{0:array,1:string} list of ['coords','distance','duration'] and the provider name */
    private function fetchAlternatives(float $fLat, float $fLng, float $tLat, float $tLng): array
    {
        $errors = [];
        if (filled(config('services.ors.key'))) {
            try {
                $res = Http::timeout(25)->withHeaders(['Authorization' => config('services.ors.key')])
                    ->post('https://api.openrouteservice.org/v2/directions/driving-car/geojson', [
                        'coordinates' => [[$fLng, $fLat], [$tLng, $tLat]],
                        'alternative_routes' => ['target_count' => 3, 'share_factor' => 0.6, 'weight_factor' => 1.6],
                    ]);
                $out = [];
                foreach ($res->json('features') ?? [] as $f) {
                    $out[] = [
                        'coords' => $f['geometry']['coordinates'],
                        'distance' => (float) ($f['properties']['summary']['distance'] ?? Geo::pathLength($f['geometry']['coordinates'])),
                        'duration' => (float) ($f['properties']['summary']['duration'] ?? 0),
                    ];
                }
                if ($out) {
                    return [$out, 'OpenRouteService'];
                }
                $errors[] = 'ORS '.$res->status();
            } catch (\Throwable $e) {
                $errors[] = 'ORS '.$e->getMessage();
            }
        }

        try {
            $url = sprintf('https://router.project-osrm.org/route/v1/driving/%F,%F;%F,%F', $fLng, $fLat, $tLng, $tLat);
            $res = Http::timeout(25)->get($url, ['alternatives' => 'true', 'overview' => 'full', 'geometries' => 'geojson', 'steps' => 'false']);
            $out = [];
            foreach ($res->json('routes') ?? [] as $r) {
                $out[] = ['coords' => $r['geometry']['coordinates'], 'distance' => (float) $r['distance'], 'duration' => (float) $r['duration']];
            }
            if ($out) {
                return [$out, 'OSRM (public demo server)'];
            }
            $errors[] = 'OSRM '.$res->status();
        } catch (\Throwable $e) {
            $errors[] = 'OSRM '.$e->getMessage();
        }

        Log::warning('Routing failed', $errors);
        throw new \RuntimeException(__('The routing service could not be reached. Check your internet connection and try again.'));
    }
}
