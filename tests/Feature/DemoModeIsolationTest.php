<?php

namespace Tests\Feature;

use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoModeIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_routes_return_404_when_demo_mode_is_disabled(): void
    {
        config(['nightlife.demo_enabled' => false]);

        $user = $this->userWithRole('user');

        $this->get('/demo')->assertNotFound();
        $this->actingAs($user)->post('/demo/incident')->assertNotFound();
        $this->actingAs($user)->post('/demo/reset')->assertNotFound();
        $this->actingAs($user)->post('/demo/switch/admin')->assertNotFound();
    }

    public function test_demo_reset_does_not_mutate_data_when_demo_mode_disabled(): void
    {
        config(['nightlife.demo_enabled' => false]);

        $user = $this->userWithRole('user');
        $venue = $this->makeVenue();
        $report = Report::create([
            'user_id' => $user->id,
            'venue_id' => $venue->id,
            'kind' => 'incident',
            'body' => 'Important production incident record',
            'incident_time' => now(),
            'status' => 'verified',
        ]);

        $this->actingAs($user)->post('/demo/reset')->assertNotFound();

        $this->assertDatabaseHas('reports', ['id' => $report->id]);
        $this->assertDatabaseHas('venues', ['id' => $venue->id]);
    }

    public function test_unauthenticated_user_cannot_switch_role_even_when_demo_enabled(): void
    {
        config(['nightlife.demo_enabled' => true]);

        $this->post('/demo/switch/admin')
            ->assertRedirect('/login');
    }

    public function test_ordinary_user_cannot_switch_role_or_reset_in_demo_mode(): void
    {
        config(['nightlife.demo_enabled' => true]);

        $citizen = $this->userWithRole('user');

        $this->actingAs($citizen)->post('/demo/switch/admin')->assertForbidden();
        $this->actingAs($citizen)->post('/demo/reset')->assertForbidden();
    }

    public function test_demo_mode_actions_are_recorded_in_audit_logs_when_enabled(): void
    {
        config(['nightlife.demo_enabled' => true]);

        $operator = $this->userWithRole('safety_admin', ['email' => 'operator@test.com']);
        $admin = $this->userWithRole('super_admin', ['email' => 'admin@test.com']);

        $res = $this->actingAs($operator)->post('/demo/switch/admin');
        $res->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'demo.role_switched',
        ]);
    }
}
