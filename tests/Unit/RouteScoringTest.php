<?php

namespace Tests\Unit;

use App\Services\RouteService;
use App\Support\Geo;
use Tests\TestCase;

class RouteScoringTest extends TestCase
{
    // straight road along latitude 23.78 from lng 90.40 to 90.41 (~1 km)
    private function road(float $lat = 23.78): array
    {
        return [[90.40, $lat], [90.405, $lat], [90.41, $lat]];
    }

    public function test_sampling_is_about_every_50_metres(): void
    {
        $pts = Geo::sample($this->road(), 50);
        $this->assertGreaterThan(15, count($pts));
        $this->assertLessThan(25, count($pts));
        $d = Geo::distance($pts[3][0], $pts[3][1], $pts[4][0], $pts[4][1]);
        $this->assertEqualsWithDelta(50, $d, 3);
    }

    public function test_incident_near_route_adds_risk_far_one_does_not(): void
    {
        $svc = new RouteService;
        $near = ['id' => 1, 'lat' => 23.7803, 'lng' => 90.405, 'weight' => 3.0]; // ~33 m away
        $far = ['id' => 2, 'lat' => 23.80, 'lng' => 90.405, 'weight' => 5.0];   // ~2 km away

        $r = $svc->scoreRoute($this->road(), [$near, $far], [], []);
        $this->assertEqualsWithDelta(3.0, $r['risk'], 0.001);
        $this->assertSame([1], $r['incident_ids']);
    }

    public function test_dark_spots_add_risk_and_havens_reduce_it(): void
    {
        $svc = new RouteService;
        $dark = [['lat' => 23.7801, 'lng' => 90.402]];
        $haven = [['lat' => 23.7805, 'lng' => 90.408, 'type' => 'police', 'name' => 'PS']];

        $this->assertEqualsWithDelta(2.0, $svc->scoreRoute($this->road(), [], $dark, [])['risk'], 0.001);
        $r = $svc->scoreRoute($this->road(), [], [], $haven);
        $this->assertEqualsWithDelta(-1.0, $r['risk'], 0.001);
        $this->assertSame(1, $r['havens']);
    }

    public function test_safest_route_is_chosen_but_never_more_than_30_percent_longer(): void
    {
        $svc = new RouteService;
        $routes = [
            ['id' => 0, 'distance_m' => 5000, 'duration_s' => 600, 'risk_per_km' => 4.0],  // fastest, risky
            ['id' => 1, 'distance_m' => 5600, 'duration_s' => 680, 'risk_per_km' => 0.5],  // +12%, safe  -> chosen
            ['id' => 2, 'distance_m' => 8000, 'duration_s' => 900, 'risk_per_km' => 0.0],  // +60%, too long
        ];
        [$fast, $safe] = $svc->choose($routes);
        $this->assertSame(0, $fast);
        $this->assertSame(1, $safe);
    }

    public function test_fastest_is_returned_when_it_is_also_safest(): void
    {
        $svc = new RouteService;
        [$fast, $safe] = $svc->choose([
            ['distance_m' => 5000, 'duration_s' => 600, 'risk_per_km' => 0.2],
            ['distance_m' => 5200, 'duration_s' => 640, 'risk_per_km' => 1.2],
        ]);
        $this->assertSame($fast, $safe);
    }

    public function test_detour_limit_rejects_route_longer_than_30_percent_even_with_zero_risk(): void
    {
        $svc = new RouteService;
        $routes = [
            ['id' => 0, 'distance_m' => 1000, 'duration_s' => 100, 'risk_per_km' => 2.5],
            ['id' => 1, 'distance_m' => 1350, 'duration_s' => 140, 'risk_per_km' => 0.0], // +35%, above 30% limit
        ];
        [$fast, $safe] = $svc->choose($routes);
        $this->assertSame(0, $fast);
        $this->assertSame(0, $safe, 'Route exceeding 30% detour limit must be rejected despite 0 risk');
    }

    public function test_grade_is_unrated_u_when_evidence_is_unmonitored(): void
    {
        $svc = new RouteService;
        $route = ['risk_per_km' => 0.0];

        // If evidence is sufficient, 0.0 risk is Grade A
        $this->assertSame('A', $svc->safetyGrade($route, 'sufficient'));

        // If corridor is unmonitored, 0.0 risk must NOT be Grade A, but Grade U (Unrated)
        $this->assertSame('U', $svc->safetyGrade($route, 'unmonitored'));
        $this->assertSame('Unrated (No Data)', $svc->gradeLabel('U', 'unmonitored'));
    }

    public function test_coordinate_validation_rejects_out_of_bounds_and_sub_20_metre_points(): void
    {
        $svc = new RouteService;

        // Coordinates outside Bangladesh (e.g. lat 15, lng 50)
        $this->expectException(\InvalidArgumentException::class);
        $svc->validateCoordinates(15.0, 50.0, 23.78, 90.40);
    }

    public function test_coordinate_validation_rejects_points_too_close(): void
    {
        $svc = new RouteService;

        // Start and destination only 2 metres apart
        $this->expectException(\InvalidArgumentException::class);
        $svc->validateCoordinates(23.78000, 90.40000, 23.78001, 90.40001);
    }

    public function test_coordinate_redaction_sanitizes_latitude_and_longitude_in_logs(): void
    {
        $svc = new RouteService;
        $rawError = 'cURL error 28: Failed to connect to router.project-osrm.org/route/v1/driving/90.40512,23.78123;90.41987,23.79456';
        $sanitized = $svc->sanitizeForLog($rawError);

        $this->assertStringNotContainsString('90.40512', $sanitized);
        $this->assertStringNotContainsString('23.78123', $sanitized);
        $this->assertStringContainsString('[COORD]', $sanitized);
    }
}
