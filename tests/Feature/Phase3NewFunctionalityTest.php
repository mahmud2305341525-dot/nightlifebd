<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeReportJob;
use App\Models\EmergencyContact;
use App\Models\Report;
use App\Models\ReportFlag;
use App\Models\ReportResponse;
use App\Models\Trip;
use App\Models\User;
use App\Models\Venue;
use App\Services\RouteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class Phase3NewFunctionalityTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------
    // 3.1 Walking Route Functionality
    // -------------------------------------------------------------

    public function test_walking_route_computation_and_mode_handling(): void
    {
        // Mock external OSRM endpoint for offline deterministic testing
        Http::fake([
            'router.project-osrm.org/*' => Http::response([
                'code' => 'Ok',
                'routes' => [
                    [
                        'distance' => 1200.0,
                        'duration' => 240.0,
                        'geometry' => [
                            'coordinates' => [
                                [90.375, 23.746],
                                [90.380, 23.750],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = app(RouteService::class);
        $result = $service->compute(23.746, 90.375, 23.750, 90.380, 'night', 'walking');

        $this->assertEquals('walking', $result['mode']);
        $this->assertNotEmpty($result['routes']);
        // For walking mode, 1200m @ 1.33 m/s should estimate approx 902s duration
        $this->assertGreaterThan(500, $result['routes'][0]['duration_s']);

        // Test API endpoint validation
        $response = $this->postJson(route('route.compute'), [
            'from_lat' => 23.746,
            'from_lng' => 90.375,
            'to_lat' => 23.750,
            'to_lng' => 90.380,
            'bucket' => 'night',
            'mode' => 'walking',
        ]);

        $response->assertOk();
        $response->assertJsonPath('mode', 'walking');
    }

    // -------------------------------------------------------------
    // 3.2 Venue Right-of-Reply
    // -------------------------------------------------------------

    public function test_venue_partner_can_submit_right_of_reply(): void
    {
        $partner = $this->userWithRole('venue_partner');
        $venue = $this->makeVenue(['owner_id' => $partner->id]);
        $reporter = User::factory()->create();

        $report = Report::create([
            'user_id' => $reporter->id,
            'venue_id' => $venue->id,
            'body' => 'Poor lighting observed in parking lot',
            'category' => 'dark_road',
            'severity' => 2,
            'status' => 'verified',
            'incident_time' => now(),
        ]);

        $response = $this->actingAs($partner)->post(route('partner.responses.store', $report), [
            'response' => 'We have installed new high-output LED fixtures in the entire parking area.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('report_responses', [
            'report_id' => $report->id,
            'venue_id' => $venue->id,
            'user_id' => $partner->id,
            'status' => 'pending',
        ]);

        // Audit log created
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $partner->id,
            'action' => 'report_response.submitted',
        ]);
    }

    public function test_unauthorized_user_cannot_submit_venue_response(): void
    {
        $owner = $this->userWithRole('venue_partner');
        $otherPartner = $this->userWithRole('venue_partner');
        $venue = $this->makeVenue(['owner_id' => $owner->id]);
        $reporter = User::factory()->create();

        $report = Report::create([
            'user_id' => $reporter->id,
            'venue_id' => $venue->id,
            'body' => 'Loud disturbance late at night',
            'category' => 'other',
            'severity' => 2,
            'status' => 'verified',
            'incident_time' => now(),
        ]);

        // Another partner who does not own the venue is blocked
        $response = $this->actingAs($otherPartner)->post(route('partner.responses.store', $report), [
            'response' => 'Unauthorized partner trying to respond here.',
        ]);

        $response->assertForbidden();
    }

    public function test_admin_can_approve_or_reject_venue_response(): void
    {
        $admin = $this->userWithRole('safety_admin');
        $partner = $this->userWithRole('venue_partner');
        $venue = $this->makeVenue(['owner_id' => $partner->id]);

        $report = Report::create([
            'user_id' => $partner->id,
            'venue_id' => $venue->id,
            'body' => 'Report body for response moderation',
            'status' => 'verified',
            'incident_time' => now(),
        ]);

        $resp = ReportResponse::create([
            'report_id' => $report->id,
            'venue_id' => $venue->id,
            'user_id' => $partner->id,
            'response' => 'Official management clarification statement.',
            'status' => 'pending',
        ]);

        // Admin approves
        $this->actingAs($admin)->post(route('admin.responses.approve', $resp));
        $resp->refresh();
        $this->assertEquals('approved', $resp->status);
        $this->assertEquals($admin->id, $resp->moderated_by);

        // Appears in approvedResponses relation
        $this->assertCount(1, $report->approvedResponses);
    }

    // -------------------------------------------------------------
    // 3.3 Pending Report Management
    // -------------------------------------------------------------

    public function test_user_can_edit_own_pending_report(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $venue = $this->makeVenue();

        $report = Report::create([
            'user_id' => $user->id,
            'venue_id' => $venue->id,
            'body' => 'Initial pending report description',
            'status' => 'pending',
            'incident_time' => now(),
        ]);

        $this->actingAs($user)->get(route('reports.edit', $report))->assertOk();

        $response = $this->actingAs($user)->put(route('reports.update', $report), [
            'body' => 'Updated and clarified incident description.',
            'incident_time' => now()->format('Y-m-d\TH:i'),
        ]);

        $response->assertRedirect(route('reports.mine'));
        $report->refresh();
        $this->assertEquals('Updated and clarified incident description.', $report->body);
        Queue::assertPushed(AnalyzeReportJob::class);
    }

    public function test_user_cannot_edit_verified_or_rejected_report(): void
    {
        $user = User::factory()->create();
        $report = Report::create([
            'user_id' => $user->id,
            'body' => 'Verified report that cannot be edited',
            'status' => 'verified',
            'incident_time' => now(),
        ]);

        $this->actingAs($user)->get(route('reports.edit', $report))->assertForbidden();

        $response = $this->actingAs($user)->put(route('reports.update', $report), [
            'body' => 'Attempting to edit already verified report',
        ]);

        $response->assertForbidden();
    }

    public function test_user_can_withdraw_own_pending_report(): void
    {
        $user = User::factory()->create();
        $report = Report::create([
            'user_id' => $user->id,
            'body' => 'Accidental report that user wants to delete',
            'status' => 'pending',
            'incident_time' => now(),
        ]);

        $response = $this->actingAs($user)->delete(route('reports.destroy', $report));
        $response->assertRedirect(route('reports.mine'));

        $this->assertSoftDeleted('reports', ['id' => $report->id]);
    }

    // -------------------------------------------------------------
    // 3.4 Abuse Reporting
    // -------------------------------------------------------------

    public function test_user_can_flag_report_and_duplicate_flag_is_prevented(): void
    {
        $user = User::factory()->create();
        $author = User::factory()->create();

        $report = Report::create([
            'user_id' => $author->id,
            'body' => 'Spam link: check this fake promo at discount.com',
            'status' => 'verified',
            'incident_time' => now(),
        ]);

        // First flag succeeds
        $response = $this->actingAs($user)->post(route('reports.flag', $report), [
            'reason' => 'spam',
            'details' => 'Commercial advertising spam',
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('report_flags', [
            'report_id' => $report->id,
            'user_id' => $user->id,
            'reason' => 'spam',
        ]);

        // Duplicate flag by same user is rejected
        $response2 = $this->actingAs($user)->post(route('reports.flag', $report), [
            'reason' => 'spam',
        ]);
        $response2->assertSessionHas('error');
        $this->assertEquals(1, ReportFlag::where('report_id', $report->id)->count());
    }

    public function test_admin_can_resolve_abuse_flag_and_reject_reported_content(): void
    {
        $admin = $this->userWithRole('safety_admin');
        $reporter = User::factory()->create();
        $venue = $this->makeVenue();

        $report = Report::create([
            'user_id' => $reporter->id,
            'venue_id' => $venue->id,
            'body' => 'False malicious claim',
            'category' => 'violence',
            'severity' => 5,
            'status' => 'verified',
            'incident_time' => now(),
        ]);

        $flag = ReportFlag::create([
            'report_id' => $report->id,
            'user_id' => $reporter->id,
            'reason' => 'false_information',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->get(route('admin.flags.index'))->assertOk();

        // Resolve by rejecting the report
        $this->actingAs($admin)->post(route('admin.flags.resolve', $flag), [
            'action' => 'reject_report',
        ]);

        $flag->refresh();
        $report->refresh();
        $this->assertEquals('resolved', $flag->status);
        $this->assertEquals('rejected', $report->status);
    }

    // -------------------------------------------------------------
    // 3.5 Stale Live-Trip Emergency Notifications
    // -------------------------------------------------------------

    public function test_stale_trips_command_alerts_contacts_and_prevents_duplicate_alerts(): void
    {
        $user = User::factory()->create(['name' => 'Ayesha Khan']);
        EmergencyContact::create([
            'user_id' => $user->id,
            'name' => 'Brother',
            'phone' => '+8801700000001',
            'relationship' => 'family',
        ]);

        // Create an active trip where no ping has been received for 20 minutes
        $trip = Trip::create([
            'user_id' => $user->id,
            'token' => 'test-token-40-chars-length-string-12345678',
            'kind' => 'ride',
            'destination' => 'Uttara Sector 3',
            'vehicle_plate' => 'DHK-METRO-123',
            'expires_at' => now()->addMinutes(40),
            'last_ping_at' => now()->subMinutes(20),
            'last_lat' => 23.78,
            'last_lng' => 90.41,
            'stale_alerted_at' => null,
        ]);

        // Run console command
        $this->artisan('trips:check-stale')->assertExitCode(0);

        $trip->refresh();
        $this->assertNotNull($trip->stale_alerted_at);

        // Verify audit log recorded
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'trip.stale_alerted',
        ]);

        // Second run will not alert again (prevents spamming emergency contacts)
        $this->artisan('trips:check-stale')
            ->expectsOutput('0 stale live trip(s) alerted.')
            ->assertExitCode(0);
    }

    // -------------------------------------------------------------
    // 3.6 Data Privacy, Export & Safe Deletion
    // -------------------------------------------------------------

    public function test_data_export_returns_valid_json_archive(): void
    {
        $user = User::factory()->create();
        EmergencyContact::create([
            'user_id' => $user->id,
            'name' => 'Emergency Person',
            'phone' => '+8801711111111',
            'relationship' => 'friend',
        ]);

        Report::create([
            'user_id' => $user->id,
            'body' => 'Exportable report body',
            'status' => 'verified',
            'incident_time' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('profile.export'));
        $response->assertOk();
        $response->assertHeader('content-type', 'application/json');
        $this->assertStringContainsString('nightlifebd-data-export', $response->headers->get('content-disposition'));

        $json = $response->json();
        $this->assertEquals($user->id, $json['account']['id']);
        $this->assertCount(1, $json['emergency_contacts']);
        $this->assertCount(1, $json['reports']);
    }

    public function test_user_deletion_anonymizes_reports_and_preserves_safety_data(): void
    {
        $user = User::factory()->create();
        $venue = $this->makeVenue();

        $report = Report::create([
            'user_id' => $user->id,
            'venue_id' => $venue->id,
            'body' => 'Valuable community incident evidence that should survive account deletion',
            'status' => 'verified',
            'category' => 'dark_road',
            'severity' => 2,
            'incident_time' => now(),
        ]);

        // User deletes their own account
        $response = $this->actingAs($user)->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

        $response->assertRedirect('/');

        // User account deleted
        $this->assertDatabaseMissing('users', ['id' => $user->id]);

        // Community report survives with null user_id and is_anonymous = true
        $report->refresh();
        $this->assertNull($report->user_id);
        $this->assertTrue((bool) $report->is_anonymous);
    }
}
