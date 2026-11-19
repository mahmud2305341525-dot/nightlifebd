<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportPrivacyAndAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_visitor_is_redirected_from_report_actions(): void
    {
        $this->get('/reports/create')->assertRedirect('/login');
        $this->post('/reports', ['kind' => 'incident', 'body' => 'Test body'])->assertRedirect('/login');
        $this->get('/my-reports')->assertRedirect('/login');
    }

    public function test_user_only_sees_their_own_reports_in_my_reports(): void
    {
        $userA = $this->userWithRole('user', ['name' => 'Alice']);
        $userB = $this->userWithRole('user', ['name' => 'Bob']);
        $venue = $this->makeVenue();

        $reportA = Report::create([
            'user_id' => $userA->id,
            'venue_id' => $venue->id,
            'kind' => 'incident',
            'body' => 'Alice private unverified incident report',
            'incident_time' => now(),
            'status' => 'pending',
        ]);

        $reportB = Report::create([
            'user_id' => $userB->id,
            'venue_id' => $venue->id,
            'kind' => 'incident',
            'body' => 'Bob private unverified incident report',
            'incident_time' => now(),
            'status' => 'pending',
        ]);

        $resA = $this->actingAs($userA)->get('/my-reports')->assertOk();
        $resA->assertSee('Alice private unverified incident report');
        $resA->assertDontSee('Bob private unverified incident report');

        $resB = $this->actingAs($userB)->get('/my-reports')->assertOk();
        $resB->assertSee('Bob private unverified incident report');
        $resB->assertDontSee('Alice private unverified incident report');
    }

    public function test_regular_user_cannot_access_admin_dashboard_or_moderation_queue(): void
    {
        $regularUser = $this->userWithRole('user');

        $this->actingAs($regularUser)->get('/admin')->assertForbidden();
        $this->actingAs($regularUser)->get('/admin/reports')->assertForbidden();
        $this->actingAs($regularUser)->get('/admin/venues')->assertForbidden();
        $this->actingAs($regularUser)->get('/admin/users')->assertForbidden();
    }

    public function test_safety_admin_can_access_moderation_but_not_user_administration(): void
    {
        $safetyAdmin = $this->userWithRole('safety_admin');

        $this->actingAs($safetyAdmin)->get('/admin')->assertOk();
        $this->actingAs($safetyAdmin)->get('/admin/reports')->assertOk();
        $this->actingAs($safetyAdmin)->get('/admin/venues')->assertOk();

        // User administration is reserved strictly for super_admin
        $this->actingAs($safetyAdmin)->get('/admin/users')->assertForbidden();
    }

    public function test_super_admin_can_access_user_administration(): void
    {
        $superAdmin = $this->userWithRole('super_admin');

        $this->actingAs($superAdmin)->get('/admin/users')->assertOk();
    }

    public function test_banned_user_is_blocked_by_middleware(): void
    {
        $bannedUser = $this->userWithRole('user', ['is_banned' => true]);

        $this->actingAs($bannedUser)->get('/my-reports')->assertForbidden();
    }
}
