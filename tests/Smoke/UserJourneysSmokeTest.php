<?php

namespace Tests\Smoke;

use App\Models\Report;
use App\Models\SafetyTimer;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UserJourneysSmokeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Journey 1: Venue Discovery & Safety Evidence Transparency
     */
    public function test_journey_1_venue_discovery_and_evidence(): void
    {
        $venue = $this->makeVenue([
            'name' => 'Gulshan Garden Cafe',
            'area' => 'Gulshan',
            'category' => 'cafe',
            'has_cctv' => true,
            'has_guard' => true,
            'lighting' => 4,
            'status' => 'approved',
        ]);

        $user = $this->userWithRole('user');
        Report::create([
            'user_id' => $user->id,
            'venue_id' => $venue->id,
            'kind' => 'review',
            'category' => 'positive',
            'body' => 'Well lit and secure parking area.',
            'incident_time' => now()->subDay(),
            'status' => 'verified',
        ]);

        // 1. Visit Explore index
        $indexRes = $this->get('/venues');
        $indexRes->assertOk();
        $indexRes->assertSee('Gulshan Garden Cafe');
        $indexRes->assertSee('Gulshan');

        // 2. View Venue Details & Safety Evidence
        $showRes = $this->get("/venues/{$venue->id}");
        $showRes->assertOk();
        $showRes->assertSee('Gulshan Garden Cafe');
        $showRes->assertSee('CCTV');
        $showRes->assertSee('Security guard');
    }

    /**
     * Journey 2: Route Planning & Alternative Comparison
     */
    public function test_journey_2_route_planning_and_comparison(): void
    {
        Http::fake([
            'router.project-osrm.org/*' => Http::response([
                'code' => 'Ok',
                'routes' => [
                    [
                        'distance' => 1200,
                        'duration' => 180,
                        'geometry' => ['coordinates' => [[90.40, 23.78], [90.405, 23.78], [90.41, 23.78]]],
                    ],
                    [
                        'distance' => 1400,
                        'duration' => 220,
                        'geometry' => ['coordinates' => [[90.40, 23.78], [90.405, 23.785], [90.41, 23.78]]],
                    ],
                ],
            ]),
        ]);

        // 1. Visit route map UI
        $pageRes = $this->get('/route');
        $pageRes->assertOk();
        $pageRes->assertSee('Find safest route');

        // 2. Compute route comparison
        $apiRes = $this->postJson('/route/compute', [
            'from_lat' => 23.7800,
            'from_lng' => 90.4000,
            'to_lat' => 23.7800,
            'to_lng' => 90.4100,
            'bucket' => 'night',
        ]);

        $apiRes->assertOk();
        $apiRes->assertJsonStructure([
            'fastest',
            'safest',
            'routes' => [
                '*' => ['distance_m', 'duration_s', 'risk', 'grade', 'coords'],
            ],
            'tradeoff',
        ]);
        $this->assertCount(2, $apiRes->json('routes'));
    }

    /**
     * Journey 3: Incident Report Submission & Citizen Tracking
     */
    public function test_journey_3_incident_report_submission_and_tracking(): void
    {
        $citizen = $this->userWithRole('user', ['name' => 'Khadija Rahman']);
        $venue = $this->makeVenue(['name' => 'Dhanmondi Lake Walk']);

        // 1. Citizen opens report submission form
        $createRes = $this->actingAs($citizen)->get('/reports/create');
        $createRes->assertOk();
        $createRes->assertSee('Report an incident or write a review');

        // 2. Submit new report
        $postRes = $this->actingAs($citizen)->post('/reports', [
            'venue_id' => $venue->id,
            'kind' => 'incident',
            'category' => 'harassment',
            'severity' => 3,
            'body' => 'Street lights were completely off near the lake corner.',
            'incident_time' => now()->subHours(2)->toDateTimeString(),
        ]);

        $postRes->assertRedirect('/my-reports');

        // 3. Citizen checks "My Reports" dashboard
        $mineRes = $this->actingAs($citizen)->get('/my-reports');
        $mineRes->assertOk();
        $mineRes->assertSee('Street lights were completely off near the lake corner.');
        $mineRes->assertSee('Pending');

        $this->assertDatabaseHas('reports', [
            'user_id' => $citizen->id,
            'body' => 'Street lights were completely off near the lake corner.',
            'status' => 'pending',
        ]);
    }

    /**
     * Journey 4: Emergency Safety Tools (SOS, Safety Timer, Fake Call)
     */
    public function test_journey_4_emergency_safety_tools(): void
    {
        $citizen = $this->userWithRole('user');
        $contact = $citizen->emergencyContacts()->create([
            'name' => 'Brother',
            'phone' => '01711223344',
            'relation' => 'Sibling',
        ]);

        // 1. Visit SOS Hub
        $sosRes = $this->actingAs($citizen)->get('/sos');
        $sosRes->assertOk();
        $sosRes->assertSee('Brother');
        $sosRes->assertSee('999');

        // 2. Visit Safety Timer & Start a Timer
        $timerRes = $this->actingAs($citizen)->get('/timer');
        $timerRes->assertOk();

        $startRes = $this->actingAs($citizen)->post('/timer', [
            'due_at' => now()->addMinutes(30)->toDateTimeString(),
            'note' => 'Walking back from university campus',
        ]);
        $startRes->assertRedirect('/timer');

        $timer = SafetyTimer::where('user_id', $citizen->id)->latest()->first();
        $this->assertNotNull($timer);
        $this->assertNull($timer->confirmed_at);

        // 3. Confirm safety (arrival check-in)
        $confirmRes = $this->actingAs($citizen)->post("/timer/{$timer->id}/confirm");
        $confirmRes->assertRedirect();
        $this->assertNotNull($timer->fresh()->confirmed_at);

        // 4. Visit Fake Call simulation tool
        $fakeCallRes = $this->actingAs($citizen)->get('/fake-call');
        $fakeCallRes->assertOk();
        $fakeCallRes->assertSee('Simulated Call Only');
    }

    /**
     * Journey 5: Admin Moderation Journey
     */
    public function test_journey_5_admin_moderation_flow(): void
    {
        $admin = $this->userWithRole('safety_admin', ['name' => 'Moderator In Charge']);
        $reporter = $this->userWithRole('user');
        $venue = $this->makeVenue(['name' => 'Banani Club']);

        $pendingReport = Report::create([
            'user_id' => $reporter->id,
            'venue_id' => $venue->id,
            'kind' => 'incident',
            'category' => 'theft',
            'severity' => 4,
            'body' => 'Bicycle snatched from parking rack at 10 PM.',
            'incident_time' => now()->subDay(),
            'status' => 'pending',
        ]);

        // 1. Admin visits dashboard
        $dashRes = $this->actingAs($admin)->get('/admin');
        $dashRes->assertOk();
        $dashRes->assertSee('Reports');

        // 2. Admin visits reports moderation queue
        $queueRes = $this->actingAs($admin)->get('/admin/reports');
        $queueRes->assertOk();
        $queueRes->assertSee('Bicycle snatched from parking rack at 10 PM.');

        // 3. Admin approves & verifies the report
        $verifyRes = $this->actingAs($admin)->post("/admin/reports/{$pendingReport->id}/verify", [
            'category' => 'theft',
            'severity' => 4,
        ]);
        $verifyRes->assertRedirect();

        // Verify status change in database
        $this->assertDatabaseHas('reports', [
            'id' => $pendingReport->id,
            'status' => 'verified',
        ]);

        // Verify audit log record
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'report.verified',
        ]);
    }
}
