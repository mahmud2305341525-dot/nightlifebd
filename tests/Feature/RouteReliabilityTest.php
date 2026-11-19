<?php

namespace Tests\Feature;

use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RouteReliabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /**
     * Test 1: Direct route and a longer alternative within detour limit.
     */
    public function test_direct_route_and_longer_alternative(): void
    {
        // No incidents — direct route should be both fastest and safest
        Http::fake([
            'router.project-osrm.org/*' => Http::response([
                'code' => 'Ok',
                'routes' => [
                    ['distance' => 1000, 'duration' => 120, 'geometry' => ['coordinates' => [[90.40, 23.78], [90.405, 23.78], [90.41, 23.78]]]],
                    ['distance' => 1200, 'duration' => 160, 'geometry' => ['coordinates' => [[90.40, 23.78], [90.405, 23.785], [90.41, 23.78]]]],
                ],
            ]),
        ]);

        $res = $this->postJson('/route/compute', [
            'from_lat' => 23.78,
            'from_lng' => 90.40,
            'to_lat' => 23.78,
            'to_lng' => 90.41,
            'bucket' => 'night',
        ])->assertOk();

        $this->assertSame(0, $res->json('fastest'));
        $this->assertSame(0, $res->json('safest'));
        $this->assertTrue($res->json('tradeoff.same_route'));
    }

    /**
     * Test 2: Routes with different incident exposure (safest selects alternative avoiding incident).
     */
    public function test_routes_with_different_incident_exposure(): void
    {
        $u = $this->userWithRole('user', ['trust_score' => 0.9]);
        Report::create([
            'user_id' => $u->id,
            'body' => 'Armed robbery',
            'lat' => 23.7801,
            'lng' => 90.405,
            'incident_time' => now()->subDays(2)->setTime(22, 0),
            'status' => 'verified',
            'category' => 'robbery',
            'severity' => 5,
        ]);

        Http::fake([
            'router.project-osrm.org/*' => Http::response([
                'code' => 'Ok',
                'routes' => [
                    ['distance' => 1000, 'duration' => 120, 'geometry' => ['coordinates' => [[90.40, 23.78], [90.405, 23.78], [90.41, 23.78]]]],
                    ['distance' => 1150, 'duration' => 150, 'geometry' => ['coordinates' => [[90.40, 23.78], [90.405, 23.788], [90.41, 23.78]]]],
                ],
            ]),
        ]);

        $res = $this->postJson('/route/compute', [
            'from_lat' => 23.78,
            'from_lng' => 90.40,
            'to_lat' => 23.78,
            'to_lng' => 90.41,
            'bucket' => 'night',
        ])->assertOk();

        $this->assertSame(0, $res->json('fastest'));
        $this->assertSame(1, $res->json('safest'));
        $this->assertSame(1, $res->json('routes.0.incidents'));
        $this->assertSame(0, $res->json('routes.1.incidents'));
        $this->assertFalse($res->json('tradeoff.same_route'));
        $this->assertStringContainsString('Avoids', $res->json('tradeoff.safest.recommendation_reason'));
    }

    /**
     * Test 3: Missing incident information does not produce Grade A (unmonitored returns Grade U).
     */
    public function test_missing_incident_information_marks_unmonitored(): void
    {
        // Zero incidents, zero dark spots, zero havens
        Http::fake([
            'router.project-osrm.org/*' => Http::response([
                'code' => 'Ok',
                'routes' => [
                    ['distance' => 2000, 'duration' => 240, 'geometry' => ['coordinates' => [[90.40, 23.78], [90.42, 23.78]]]],
                ],
            ]),
        ]);

        $res = $this->postJson('/route/compute', [
            'from_lat' => 23.78,
            'from_lng' => 90.40,
            'to_lat' => 23.78,
            'to_lng' => 90.42,
            'bucket' => 'night',
        ])->assertOk();

        $this->assertSame('unmonitored', $res->json('confidence.level'));
        $this->assertSame('U', $res->json('routes.0.grade'));
        $this->assertStringContainsString('Unrated', $res->json('routes.0.grade_label'));
        $this->assertStringContainsString('Unmonitored corridor', $res->json('summary'));
    }

    /**
     * Test 4: Invalid or out-of-range coordinates are rejected with 422.
     */
    public function test_invalid_or_out_of_range_coordinates_rejected(): void
    {
        // Out of Bangladesh latitude (e.g. 10.0 instead of 20-27)
        $this->postJson('/route/compute', [
            'from_lat' => 10.0,
            'from_lng' => 90.40,
            'to_lat' => 23.78,
            'to_lng' => 90.41,
        ])->assertStatus(422);

        // Missing coordinates
        $this->postJson('/route/compute', [
            'from_lat' => 23.78,
        ])->assertStatus(422);
    }

    /**
     * Test 5: Coordinates that are too close (< 20m) return 422.
     */
    public function test_coordinates_too_close_return_422(): void
    {
        $this->postJson('/route/compute', [
            'from_lat' => 23.78000,
            'from_lng' => 90.40000,
            'to_lat' => 23.78001,
            'to_lng' => 90.40001,
        ])->assertStatus(422)
            ->assertJsonStructure(['error']);
    }

    /**
     * Test 6: No route available from provider (e.g. OSRM code NoRoute).
     */
    public function test_no_route_available_returns_descriptive_error(): void
    {
        Http::fake([
            'router.project-osrm.org/*' => Http::response([
                'code' => 'NoRoute',
                'message' => 'Impossible route between points',
                'routes' => [],
            ], 200),
        ]);

        $res = $this->postJson('/route/compute', [
            'from_lat' => 23.78,
            'from_lng' => 90.40,
            'to_lat' => 23.85,
            'to_lng' => 90.45,
        ])->assertStatus(503);

        $this->assertStringContainsString('No drivable route found', $res->json('error'));
    }

    /**
     * Test 7: External-service timeout returns descriptive 503 error.
     */
    public function test_external_service_timeout_handled_gracefully(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Operation timed out');
        });

        $res = $this->postJson('/route/compute', [
            'from_lat' => 23.78,
            'from_lng' => 90.40,
            'to_lat' => 23.79,
            'to_lng' => 90.41,
        ])->assertStatus(503);

        $this->assertStringContainsString('timed out', $res->json('error'));
    }

    /**
     * Test 8: Malformed provider response handled cleanly without uncaught exceptions.
     */
    public function test_malformed_provider_response_handled(): void
    {
        Http::fake([
            'router.project-osrm.org/*' => Http::response('corrupted html body', 200, ['Content-Type' => 'text/html']),
        ]);

        $res = $this->postJson('/route/compute', [
            'from_lat' => 23.78,
            'from_lng' => 90.40,
            'to_lat' => 23.79,
            'to_lng' => 90.41,
        ])->assertStatus(503);

        $this->assertNotEmpty($res->json('error'));
    }

    /**
     * Test 9: Detour limit strictly enforced (alternative > 30% longer rejected).
     */
    public function test_detour_limit_strictly_enforced(): void
    {
        $u = $this->userWithRole('user', ['trust_score' => 0.9]);
        Report::create([
            'user_id' => $u->id,
            'body' => 'Incident',
            'lat' => 23.7801,
            'lng' => 90.405,
            'incident_time' => now()->subDay()->setTime(22, 0),
            'status' => 'verified',
            'category' => 'assault',
            'severity' => 4,
        ]);

        // Route 0 is 1000m (risky). Route 1 is 1400m (+40% longer, safe).
        Http::fake([
            'router.project-osrm.org/*' => Http::response([
                'code' => 'Ok',
                'routes' => [
                    ['distance' => 1000, 'duration' => 120, 'geometry' => ['coordinates' => [[90.40, 23.78], [90.405, 23.78], [90.41, 23.78]]]],
                    ['distance' => 1400, 'duration' => 190, 'geometry' => ['coordinates' => [[90.40, 23.78], [90.405, 23.795], [90.41, 23.78]]]],
                ],
            ]),
        ]);

        $res = $this->postJson('/route/compute', [
            'from_lat' => 23.78,
            'from_lng' => 90.40,
            'to_lat' => 23.78,
            'to_lng' => 90.41,
        ])->assertOk();

        // Since route 1 exceeds the 30% detour limit (1400m > 1300m limit), safest must default to fastest
        $this->assertSame(0, $res->json('fastest'));
        $this->assertSame(0, $res->json('safest'));
    }

    /**
     * Test 10: Consistent distance, duration, and risk units.
     */
    public function test_consistent_distance_and_duration_units(): void
    {
        Http::fake([
            'router.project-osrm.org/*' => Http::response([
                'code' => 'Ok',
                'routes' => [
                    ['distance' => 3500, 'duration' => 420, 'geometry' => ['coordinates' => [[90.40, 23.78], [90.41, 23.78], [90.42, 23.78]]]],
                ],
            ]),
        ]);

        $res = $this->postJson('/route/compute', [
            'from_lat' => 23.78,
            'from_lng' => 90.40,
            'to_lat' => 23.78,
            'to_lng' => 90.42,
        ])->assertOk();

        // Check backend route array units
        $this->assertSame(3500, $res->json('routes.0.distance_m'));
        $this->assertSame(420, $res->json('routes.0.duration_s'));
        $this->assertIsNumeric($res->json('routes.0.risk_per_km'));

        // Check frontend Pareto tradeoff units
        $tradeoff = $res->json('tradeoff.fastest');
        $this->assertEqualsWithDelta(3.5, $tradeoff['distance_km'], 0.1);
        $this->assertSame(7, $tradeoff['duration_min']); // 420 / 60 = 7
    }

    /**
     * Test 11: Route that cannot be meaningfully compared due to insufficient evidence.
     */
    public function test_route_cannot_be_meaningfully_compared_when_insufficient_evidence(): void
    {
        Http::fake([
            'router.project-osrm.org/*' => Http::response([
                'code' => 'Ok',
                'routes' => [
                    ['distance' => 1000, 'duration' => 120, 'geometry' => ['coordinates' => [[90.40, 23.78], [90.405, 23.78], [90.41, 23.78]]]],
                    ['distance' => 1100, 'duration' => 140, 'geometry' => ['coordinates' => [[90.40, 23.78], [90.405, 23.785], [90.41, 23.78]]]],
                ],
            ]),
        ]);

        $res = $this->postJson('/route/compute', [
            'from_lat' => 23.78,
            'from_lng' => 90.40,
            'to_lat' => 23.78,
            'to_lng' => 90.41,
        ])->assertOk();

        $this->assertSame('unmonitored', $res->json('confidence.level'));
        $this->assertSame('U', $res->json('routes.0.grade'));
        $this->assertSame('U', $res->json('routes.1.grade'));
        $this->assertStringContainsString('Advisory only', $res->json('confidence.disclaimer'));
    }
}
