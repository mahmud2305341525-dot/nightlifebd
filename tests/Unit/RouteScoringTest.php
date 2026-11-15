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
        $svc = new RouteService();
        $near = ['id' => 1, 'lat' => 23.7803, 'lng' => 90.405, 'weight' => 3.0]; // ~33 m away
        $far = ['id' => 2, 'lat' => 23.80, 'lng' => 90.405, 'weight' => 5.0];   // ~2 km away

        $r = $svc->scoreRoute($this->road(), [$near, $far], [], []);
        $this->assertEqualsWithDelta(3.0, $r['risk'], 0.001);
        $this->assertSame([1], $r['incident_ids']);
    }

    public function test_dark_spots_add_risk_and_havens_reduce_it(): void
    {
        $svc = new RouteService();
        $dark = [['lat' => 23.7801, 'lng' => 90.402]];
        $haven = [['lat' => 23.7805, 'lng' => 90.408, 'type' => 'police', 'name' => 'PS']];

        $this->assertEqualsWithDelta(2.0, $svc->scoreRoute($this->road(), [], $dark, [])['risk'], 0.001);
        $r = $svc->scoreRoute($this->road(), [], [], $haven);
        $this->assertEqualsWithDelta(-1.0, $r['risk'], 0.001);
        $this->assertSame(1, $r['havens']);
    }

    public function test_safest_route_is_chosen_but_never_more_than_30_percent_longer(): void
    {
        $svc = new RouteService();
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
        $svc = new RouteService();
        [$fast, $safe] = $svc->choose([
            ['distance_m' => 5000, 'duration_s' => 600, 'risk_per_km' => 0.2],
            ['distance_m' => 5200, 'duration_s' => 640, 'risk_per_km' => 1.2],
        ]);
        $this->assertSame($fast, $safe);
    }
}
