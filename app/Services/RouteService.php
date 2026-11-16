<?php

namespace App\Services;

use App\Models\DarkSpot;
use App\Models\Report;
use App\Models\SafeHaven;
use App\Support\Geo;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Multi-objective routing service (Fastest vs Safest vs Well-Lit).
 * Integrates external routing APIs (ORS / OSRM) with NightLifeBD safety layers:
 *  - Samples coordinates along alternative routes (~50 m intervals).
 *  - Scores each route against verified community incidents, dark spots, and safe havens.
 *  - Applies a strict 30% detour threshold (routes > 30% longer than fastest are rejected).
 *  - Categorizes evidence confidence (sufficient, limited, unmonitored) so that unreported
 *    streets are never equated with safe streets (no zero-evidence Grade A).
 */
class RouteService
{
    public const INCIDENT_RADIUS = 100;  // metres

    public const HAVEN_RADIUS = 150;     // metres

    public const DARK_SPOT_RISK = 2.0;

    public const HAVEN_BONUS = 1.0;

    public const MIN_COORDINATE_DISTANCE = 20.0; // metres

    /**
     * @return array{
     *     routes: array,
     *     fastest: int,
     *     safest: int,
     *     well_lit: int,
     *     summary: string,
     *     tradeoff: array,
     *     confidence: array{
     *         level: string,
     *         freshness: string,
     *         total_evidence: int,
     *         unverified_alerts: int,
     *         disclaimer: string
     *     },
     *     source: string
     * }
     *
     * @throws \InvalidArgumentException when coordinates are invalid or too close
     * @throws \RuntimeException when no routing service is reachable or destination is unreachable
     */
    public function compute(float $fromLat, float $fromLng, float $toLat, float $toLng, string $bucket): array
    {
        $this->validateCoordinates($fromLat, $fromLng, $toLat, $toLng);

        [$raw, $source] = $this->fetchAlternatives($fromLat, $fromLng, $toLat, $toLng);

        $all = [];
        foreach ($raw as $r) {
            foreach ($r['coords'] as $c) {
                $all[] = $c;
            }
        }
        $data = $this->riskData(Geo::bbox($all), $bucket);

        $totalEvidence = count($data['incidents']) + count($data['dark_spots']) + count($data['havens']);
        $evidenceLevel = $this->evaluateEvidenceLevel($totalEvidence);
        $freshness = $this->evaluateFreshness($data['recent_incidents'], $data['stale_incidents']);

        $routes = [];
        foreach ($raw as $i => $r) {
            $score = $this->scoreRoute($r['coords'], $data['incidents'], $data['dark_spots'], $data['havens']);
            $km = max(0.05, $r['distance'] / 1000);
            $riskPerKm = round(max(0, $score['risk']) / $km, 2);

            $routes[] = [
                'id' => $i,
                'distance_m' => (int) round($r['distance']),
                'duration_s' => (int) round($r['duration']),
                'risk' => round($score['risk'], 2),
                'risk_per_km' => $riskPerKm,
                'incidents' => count($score['incident_ids']),
                'dark_spots' => $score['dark_spots'],
                'havens' => $score['havens'],
                'haven_list' => $score['haven_list'],
                'coords' => array_map(fn ($c) => [round($c[1], 5), round($c[0], 5)], $r['coords']), // [lat,lng] for Leaflet
                'evidence_count' => count($score['incident_ids']) + $score['dark_spots'] + $score['havens'],
            ];
        }

        [$fastest, $safest] = $this->choose($routes);
        $wellLit = $this->chooseWellLit($routes, $fastest);

        // Assign safety grades
        foreach ($routes as &$rt) {
            $rt['grade'] = $this->safetyGrade($rt, $evidenceLevel);
            $rt['grade_label'] = $this->gradeLabel($rt['grade'], $evidenceLevel);
        }
        unset($rt);

        return [
            'routes' => $routes,
            'fastest' => $fastest,
            'safest' => $safest,
            'well_lit' => $wellLit,
            'summary' => $this->summary($routes[$fastest], $routes[$safest], $fastest === $safest, $evidenceLevel),
            'tradeoff' => $this->tradeoff($routes, $fastest, $safest, $wellLit, $evidenceLevel),
            'confidence' => [
                'level' => $evidenceLevel,
                'freshness' => $freshness,
                'total_evidence' => $totalEvidence,
                'unverified_alerts' => $data['unverified_count'] ?? 0,
                'disclaimer' => __('Advisory only. No route can be guaranteed safe. Unreported corridors may have unknown risks.'),
            ],
            'source' => $source,
        ];
    }

    /**
     * Validate coordinates for Bangladesh boundaries and minimum distance.
     */
    public function validateCoordinates(float $fLat, float $fLng, float $tLat, float $tLng): void
    {
        if (! is_finite($fLat) || ! is_finite($fLng) || ! is_finite($tLat) || ! is_finite($tLng)) {
            throw new \InvalidArgumentException(__('Coordinates must be valid finite numbers.'));
        }

        // Bangladesh boundary check
        if ($fLat < 20.0 || $fLat > 27.0 || $fLng < 88.0 || $fLng > 93.0 ||
            $tLat < 20.0 || $tLat > 27.0 || $tLng < 88.0 || $tLng > 93.0) {
            throw new \InvalidArgumentException(__('Coordinates must be within Bangladesh boundaries (Lat 20-27, Lng 88-93).'));
        }

        $distance = Geo::distance($fLat, $fLng, $tLat, $tLng);
        if ($distance < self::MIN_COORDINATE_DISTANCE) {
            throw new \InvalidArgumentException(__('Start and destination coordinates are too close (minimum :min metres).', ['min' => (int) self::MIN_COORDINATE_DISTANCE]));
        }
    }

    /**
     * Categorize evidence level based on total corroborating data points.
     */
    public function evaluateEvidenceLevel(int $totalEvidence): string
    {
        if ($totalEvidence === 0) {
            return 'unmonitored';
        }
        if ($totalEvidence < 3) {
            return 'limited';
        }

        return 'sufficient';
    }

    /**
     * Evaluate data freshness from recent vs stale incident breakdown.
     */
    public function evaluateFreshness(int $recent, int $stale): string
    {
        if ($recent > 0) {
            return 'recent';
        }
        if ($stale > 0) {
            return 'stale';
        }

        return 'none';
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

    /** Pick the route with fewest dark spots within the detour limit. */
    public function chooseWellLit(array $routes, int $fastest): int
    {
        $limit = $routes[$fastest]['distance_m'] * config('nightlife.max_detour', 1.3);
        $best = $fastest;
        foreach ($routes as $i => $r) {
            if ($r['distance_m'] > $limit) {
                continue;
            }
            if ($r['dark_spots'] < $routes[$best]['dark_spots']
                || ($r['dark_spots'] === $routes[$best]['dark_spots'] && $r['duration_s'] < $routes[$best]['duration_s'])) {
                $best = $i;
            }
        }

        return $best;
    }

    /** Map risk_per_km to a letter grade A–F, or U for unmonitored. */
    public function safetyGrade(array $route, string $evidenceLevel = 'sufficient'): string
    {
        if ($evidenceLevel === 'unmonitored') {
            return 'U';
        }

        $rpk = $route['risk_per_km'];
        if ($rpk <= 0.5) {
            return 'A';
        }
        if ($rpk <= 1.5) {
            return 'B';
        }
        if ($rpk <= 3.0) {
            return 'C';
        }
        if ($rpk <= 5.0) {
            return 'D';
        }
        if ($rpk <= 8.0) {
            return 'E';
        }

        return 'F';
    }

    public function gradeLabel(string $grade, string $evidenceLevel): string
    {
        if ($grade === 'U') {
            return __('Unrated (No Data)');
        }
        if ($evidenceLevel === 'limited') {
            return __('Grade :g (Limited Data)', ['g' => $grade]);
        }

        return __('Grade :g', ['g' => $grade]);
    }

    /** Build structured Pareto tradeoff analysis with explicit reasons. */
    private function tradeoff(array $routes, int $fastest, int $safest, int $wellLit, string $evidenceLevel): array
    {
        $f = $routes[$fastest];
        $s = $routes[$safest];
        $w = $routes[$wellLit];

        $reasons = $this->determineReasons($f, $s, $w, $fastest, $safest, $wellLit, $evidenceLevel);

        $build = function (array $r, string $label, string $reason) use ($f, $evidenceLevel) {
            return [
                'label' => $label,
                'distance_km' => round($r['distance_m'] / 1000, 1),
                'duration_min' => (int) round($r['duration_s'] / 60),
                'extra_min' => max(0, (int) round(($r['duration_s'] - $f['duration_s']) / 60)),
                'extra_km' => round(max(0, ($r['distance_m'] - $f['distance_m'])) / 1000, 1),
                'risk_per_km' => $r['risk_per_km'],
                'grade' => $r['grade'],
                'grade_label' => $this->gradeLabel($r['grade'], $evidenceLevel),
                'incidents' => $r['incidents'],
                'dark_spots' => $r['dark_spots'],
                'havens' => $r['havens'],
                'evidence_level' => $evidenceLevel,
                'recommendation_reason' => $reason,
            ];
        };

        $items = [
            'fastest' => $build($f, __('Fastest'), $reasons['fastest']),
            'safest' => $build($s, __('Safest'), $reasons['safest']),
        ];

        // Only add well-lit if it's a distinct route from fastest and safest
        if ($wellLit !== $fastest && $wellLit !== $safest) {
            $items['well_lit'] = $build($w, __('Well-Lit Corridor'), $reasons['well_lit']);
        }

        $items['same_route'] = ($fastest === $safest);

        return $items;
    }

    public function determineReasons(array $f, array $s, array $w, int $fastest, int $safest, int $wellLit, string $evidenceLevel): array
    {
        $same = ($fastest === $safest);
        if ($same) {
            if ($evidenceLevel === 'unmonitored') {
                $fastReason = __('Selected as fastest path. The corridor has no recorded community reports to assess comparative risk.');
                $safeReason = $fastReason;
            } else {
                $fastReason = __('Selected as direct fastest path with lowest risk exposure (:risk risk/km).', ['risk' => $f['risk_per_km']]);
                $safeReason = $fastReason;
            }
        } else {
            $fastReason = __('Direct fastest route, but passes :n incident area(s) and :d dark spot(s).', [
                'n' => $f['incidents'],
                'd' => $f['dark_spots'],
            ]);
            $extraMin = max(0, (int) round(($s['duration_s'] - $f['duration_s']) / 60));
            $extraKm = round(max(0, ($s['distance_m'] - $f['distance_m'])) / 1000, 1);
            $detourPct = $f['distance_m'] > 0 ? (int) round((($s['distance_m'] - $f['distance_m']) / $f['distance_m']) * 100) : 0;
            $safeReason = __('Recommended safest route. Avoids :avoided incident area(s) and :dark dark spot(s), adding :min min (+:km km, :pct% detour within 30% limit).', [
                'avoided' => max(0, $f['incidents'] - $s['incidents']),
                'dark' => max(0, $f['dark_spots'] - $s['dark_spots']),
                'min' => $extraMin,
                'km' => $extraKm,
                'pct' => $detourPct,
            ]);
        }

        $wellLitReason = __('Corridor with fewest unlit spots (:d dark spot(s)).', ['d' => $w['dark_spots']]);

        return [
            'fastest' => $fastReason,
            'safest' => $safeReason,
            'well_lit' => $wellLitReason,
        ];
    }

    private function summary(array $fast, array $safe, bool $same, string $evidenceLevel): string
    {
        if ($same) {
            if ($evidenceLevel === 'unmonitored') {
                return __('Fastest path selected. Unmonitored corridor with 0 community reports — exercise standard nighttime caution.');
            }

            return __('The fastest route is also the safest option we found (:n incident area(s) nearby).', ['n' => $fast['incidents']]);
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

        // Push bbox filter into SQL — avoids loading every report into PHP memory.
        $reports = Report::query()
            ->verified()
            ->whereNull('duplicate_of')
            ->where(fn ($q) => $q->where('category', '!=', 'positive')->orWhereNull('category'))
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->whereBetween('lat', [$minLat, $maxLat])
            ->whereBetween('lng', [$minLng, $maxLng])
            ->with(['user:id,trust_score', 'venue:id,lat,lng'])
            ->withCount('duplicates')
            ->get();

        $incidents = [];
        $recentCount = 0;
        $staleCount = 0;

        foreach ($reports as $r) {
            $c = $r->coords();
            if (! $c) {
                continue;
            }
            $ageDays = max(0, (int) (now()->diffInDays($r->incident_time, false) * -1));
            if ($ageDays <= 90) {
                $recentCount++;
            } else {
                $staleCount++;
            }
            $weight = ((int) $r->severity ?: 2)
                * (float) ($r->user->trust_score ?? 0.5)
                * exp(-$ageDays / 30)
                * ($r->bucket() === $bucket ? 1.0 : 0.3)
                * (1 + 0.25 * min(3, $r->duplicates_count));
            $incidents[] = [
                'id' => $r->id,
                'lat' => $c[0],
                'lng' => $c[1],
                'weight' => $weight,
                'age_days' => $ageDays,
            ];
        }

        // Count pending unverified reports inside the bbox for advisory notice
        $unverifiedCount = Report::query()
            ->where('status', 'pending')
            ->whereBetween('lat', [$minLat, $maxLat])
            ->whereBetween('lng', [$minLng, $maxLng])
            ->count();

        $dark = DarkSpot::whereBetween('lat', [$minLat, $maxLat])->whereBetween('lng', [$minLng, $maxLng])
            ->get(['lat', 'lng'])->map(fn ($d) => ['lat' => $d->lat, 'lng' => $d->lng])->all();

        $havens = SafeHaven::whereBetween('lat', [$minLat, $maxLat])->whereBetween('lng', [$minLng, $maxLng])
            ->get(['type', 'name', 'lat', 'lng'])->map(fn ($h) => ['type' => $h->type, 'name' => $h->name, 'lat' => $h->lat, 'lng' => $h->lng])->all();

        return [
            'incidents' => $incidents,
            'dark_spots' => $dark,
            'havens' => $havens,
            'recent_incidents' => $recentCount,
            'stale_incidents' => $staleCount,
            'unverified_count' => $unverifiedCount,
        ];
    }

    /** @return array{0:array,1:string} list of ['coords','distance','duration'] and the provider name */
    private function fetchAlternatives(float $fLat, float $fLng, float $tLat, float $tLng): array
    {
        $cacheKey = sprintf('route_geom_%s_%s_%s_%s', round($fLat, 4), round($fLng, 4), round($tLat, 4), round($tLng, 4));

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($fLat, $fLng, $tLat, $tLng) {
            return $this->fetchFromProviders($fLat, $fLng, $tLat, $tLng);
        });
    }

    /**
     * Call external routing APIs (ORS / OSRM) with robust error classification and sanitized logging.
     *
     * @return array{0:array, 1:string}
     */
    public function fetchFromProviders(float $fLat, float $fLng, float $tLat, float $tLng): array
    {
        $errors = [];

        // 1. Try OpenRouteService if API key configured
        if (filled(config('services.ors.key'))) {
            try {
                $res = Http::timeout(10)->withHeaders(['Authorization' => config('services.ors.key')])
                    ->post('https://api.openrouteservice.org/v2/directions/driving-car/geojson', [
                        'coordinates' => [[$fLng, $fLat], [$tLng, $tLat]],
                        'alternative_routes' => ['target_count' => 3, 'share_factor' => 0.6, 'weight_factor' => 1.6],
                    ]);

                if ($res->successful()) {
                    $features = $res->json('features') ?? [];
                    $out = [];
                    foreach ($features as $f) {
                        if (isset($f['geometry']['coordinates']) && is_array($f['geometry']['coordinates']) && count($f['geometry']['coordinates']) > 0) {
                            $out[] = [
                                'coords' => $f['geometry']['coordinates'],
                                'distance' => (float) ($f['properties']['summary']['distance'] ?? Geo::pathLength($f['geometry']['coordinates'])),
                                'duration' => (float) ($f['properties']['summary']['duration'] ?? 0),
                            ];
                        }
                    }
                    if ($out) {
                        return [$out, 'OpenRouteService'];
                    }
                    $errors[] = 'ORS returned empty routes';
                } elseif ($res->status() === 429) {
                    $errors[] = 'ORS 429 (rate limited)';
                } else {
                    $errors[] = 'ORS HTTP '.$res->status();
                }
            } catch (ConnectionException $e) {
                $errors[] = 'ORS timeout';
            } catch (\Throwable $e) {
                $errors[] = 'ORS: '.$this->sanitizeForLog($e->getMessage());
            }
        }

        // 2. Fallback to public OSRM server
        try {
            $url = sprintf('https://router.project-osrm.org/route/v1/driving/%F,%F;%F,%F', $fLng, $fLat, $tLng, $tLat);
            $res = Http::timeout(15)->get($url, [
                'alternatives' => 'true',
                'overview' => 'full',
                'geometries' => 'geojson',
                'steps' => 'false',
            ]);

            if ($res->successful()) {
                $json = $res->json();
                if (! is_array($json)) {
                    $errors[] = 'OSRM malformed JSON';
                } elseif (($json['code'] ?? '') === 'NoRoute') {
                    throw new \RuntimeException(__('No drivable route found between these locations. The start or destination may be inaccessible by road.'));
                } else {
                    $routes = $json['routes'] ?? [];
                    $out = [];
                    foreach ($routes as $r) {
                        if (isset($r['geometry']['coordinates']) && is_array($r['geometry']['coordinates']) && count($r['geometry']['coordinates']) > 0) {
                            $out[] = [
                                'coords' => $r['geometry']['coordinates'],
                                'distance' => (float) ($r['distance'] ?? 0),
                                'duration' => (float) ($r['duration'] ?? 0),
                            ];
                        }
                    }
                    if ($out) {
                        return [$out, 'OSRM (public demo server)'];
                    }
                    $errors[] = 'OSRM returned 0 routes';
                }
            } elseif ($res->status() === 429) {
                throw new \RuntimeException(__('Routing service rate limit reached. Please wait a moment and try again.'));
            } else {
                $errors[] = 'OSRM HTTP '.$res->status();
            }
        } catch (ConnectionException $e) {
            $errors[] = 'OSRM connection timeout';
            throw new \RuntimeException(__('Routing service timed out. Please try again in a few moments.'));
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $errors[] = 'OSRM: '.$this->sanitizeForLog($e->getMessage());
        }

        // Mask coordinates in warning log
        Log::warning('Routing failed: '.implode('; ', $errors));
        throw new \RuntimeException(__('The routing service could not be reached. Check your internet connection and try again.'));
    }

    /**
     * Mask coordinates and numbers to avoid leaking trip locations in log files.
     */
    public function sanitizeForLog(string $msg): string
    {
        return (string) preg_replace('/\b\d{1,3}\.\d{4,}\b/', '[COORD]', $msg);
    }
}
