<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Services\NightPlannerService;
use App\Services\SafetyScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RouteAndPlannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_endpoint_prefers_the_route_that_avoids_a_verified_incident(): void
    {
        $u = $this->userWithRole('user', ['trust_score' => 0.9]);
        // incident sits on the "direct" road only
        Report::create(['user_id' => $u->id, 'body' => 'phone snatched', 'lat' => 23.7801, 'lng' => 90.405, 'incident_time' => now()->subDay()->setTime(23, 0),
            'status' => 'verified', 'category' => 'theft', 'severity' => 4]);

        Http::fake(['router.project-osrm.org/*' => Http::response(['routes' => [
            ['distance' => 1000, 'duration' => 120, 'geometry' => ['coordinates' => [[90.40, 23.78], [90.405, 23.78], [90.41, 23.78]]]],
            ['distance' => 1150, 'duration' => 150, 'geometry' => ['coordinates' => [[90.40, 23.78], [90.405, 23.786], [90.41, 23.78]]]],
        ]])]);

        $res = $this->postJson('/route/compute', ['from_lat' => 23.78, 'from_lng' => 90.40, 'to_lat' => 23.78, 'to_lng' => 90.41, 'bucket' => 'night'])->assertOk();
        $this->assertSame(0, $res->json('fastest'));
        $this->assertSame(1, $res->json('safest'));
        $this->assertSame(1, $res->json('routes.0.incidents'));
        $this->assertSame(0, $res->json('routes.1.incidents'));
    }

    public function test_route_endpoint_degrades_gracefully_when_routing_is_down(): void
    {
        Http::fake(['*' => Http::response('down', 500)]);
        $this->postJson('/route/compute', ['from_lat' => 23.78, 'from_lng' => 90.40, 'to_lat' => 23.79, 'to_lng' => 90.41])
            ->assertStatus(503)->assertJsonStructure(['error']);
    }

    public function test_planner_understands_bangla_request(): void
    {
        $f = app(NightPlannerService::class)->extractFilters(['text' => '৩ জন, ৩০০০ টাকা, শান্ত জায়গা, ধানমন্ডি']);
        $this->assertSame(3, $f['group']);
        $this->assertSame(3000, $f['budget']);
        $this->assertSame('Dhanmondi', $f['area']);
        $this->assertContains('quiet', $f['vibes']);
    }

    public function test_planner_respects_budget_and_excludes_caution_venues(): void
    {
        $ok = $this->makeVenue(['name' => 'Quiet Cafe', 'area' => 'Dhanmondi', 'price_tier' => 1, 'vibe_tags' => 'quiet,coffee']);
        $bad = $this->makeVenue(['name' => 'Risky Spot', 'area' => 'Dhanmondi', 'price_tier' => 1, 'has_cctv' => false, 'has_guard' => false, 'lighting' => 1]);
        $u = $this->userWithRole('user', ['trust_score' => 0.9]);
        foreach (range(1, 4) as $_) {
            Report::create(['user_id' => $u->id, 'venue_id' => $bad->id, 'body' => 'harassment', 'incident_time' => now()->subDay()->setTime(21, 30),
                'status' => 'verified', 'category' => 'harassment', 'severity' => 4]);
        }
        $svc = app(SafetyScoreService::class);
        $svc->recalcVenue($ok);
        $svc->recalcVenue($bad);

        $res = $this->post('/planner', ['text' => 'quiet place', 'budget' => 1000, 'group' => 2, 'area' => 'Dhanmondi', 'hour' => 21])->assertOk();
        $res->assertSee('Quiet Cafe');
        $res->assertDontSee('Risky Spot');
    }
}
