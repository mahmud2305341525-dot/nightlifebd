<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductionSecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_routes_are_unavailable_when_demo_mode_is_disabled(): void
    {
        config(['nightlife.demo_enabled' => false]);

        $this->get('/demo')->assertStatus(404);
        $this->post('/demo/switch/admin')->assertStatus(404);
        $this->post('/demo/incident')->assertStatus(404);
        $this->post('/demo/timer')->assertStatus(404);
        $this->post('/demo/spam')->assertStatus(404);
        $this->post('/demo/reset')->assertStatus(404);
    }

    public function test_enabling_app_debug_alone_does_not_expose_demo_routes(): void
    {
        config([
            'app.debug' => true,
            'nightlife.demo_enabled' => false,
        ]);

        $this->get('/demo')->assertStatus(404);
        $this->post('/demo/switch/admin')->assertStatus(404);
        $this->post('/demo/reset')->assertStatus(404);
    }

    public function test_unauthenticated_visitor_cannot_switch_demo_roles_or_reset_state(): void
    {
        config(['nightlife.demo_enabled' => true]);

        // Unauthenticated visitor must be stopped from switching roles or resetting database
        $this->post('/demo/switch/admin')->assertRedirect('/login');
        $this->post('/demo/reset')->assertRedirect('/login');
        $this->post('/demo/incident')->assertRedirect('/login');
    }

    public function test_ordinary_user_cannot_switch_roles_or_reset_state_in_demo_mode(): void
    {
        config(['nightlife.demo_enabled' => true]);

        $citizen = $this->userWithRole('user');

        // Ordinary user must receive 403 Forbidden when trying to switch accounts or reset state
        $this->actingAs($citizen)->post('/demo/switch/admin')->assertForbidden();
        $this->actingAs($citizen)->post('/demo/reset')->assertForbidden();
    }

    public function test_authorized_staff_can_switch_roles_when_demo_mode_enabled(): void
    {
        config(['nightlife.demo_enabled' => true]);

        $staffOperator = $this->userWithRole('safety_admin', ['email' => 'operator@test.com']);
        $adminUser = $this->userWithRole('super_admin', ['email' => 'admin@test.com']);

        // Authenticated staff user can view demo command center
        $this->actingAs($staffOperator)
            ->get('/demo')
            ->assertStatus(200);

        // Authenticated staff user can switch to admin in demo mode
        $this->actingAs($staffOperator)
            ->post('/demo/switch/admin')
            ->assertRedirect();

        $this->assertAuthenticatedAs($adminUser);
    }

    public function test_ordinary_user_cannot_access_super_admin_or_moderator_actions(): void
    {
        $user = $this->userWithRole('user');
        $target = $this->userWithRole('user');

        // Ordinary citizen cannot view or change user roles
        $this->actingAs($user)->get('/admin/users')->assertStatus(403);
        $this->actingAs($user)->patch("/admin/users/{$target->id}", ['role' => 'super_admin'])->assertStatus(403);

        // Ordinary citizen cannot access moderation endpoints
        $this->actingAs($user)->get('/admin/reports')->assertStatus(403);
        $this->actingAs($user)->get('/admin/venues')->assertStatus(403);
    }

    public function test_user_cannot_modify_or_delete_another_users_emergency_contacts(): void
    {
        $victim = $this->userWithRole('user');
        $attacker = $this->userWithRole('user');

        $contact = $victim->emergencyContacts()->create([
            'name' => 'Victim Parent',
            'phone' => '01711111111',
            'relation' => 'Parent',
        ]);

        $this->actingAs($attacker)
            ->delete("/contacts/{$contact->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('emergency_contacts', ['id' => $contact->id]);
    }

    public function test_user_cannot_confirm_or_cancel_another_users_safety_timer(): void
    {
        $victim = $this->userWithRole('user');
        $attacker = $this->userWithRole('user');

        $timer = $victim->safetyTimers()->create([
            'due_at' => now()->addMinutes(30),
            'note' => 'Walking home safe',
        ]);

        // Attacker attempts to cancel victim's dead-man switch
        $this->actingAs($attacker)
            ->post("/timer/{$timer->id}/confirm")
            ->assertStatus(403);

        $this->actingAs($attacker)
            ->delete("/timer/{$timer->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('safety_timers', [
            'id' => $timer->id,
            'confirmed_at' => null,
        ]);
    }

    public function test_expired_or_revoked_trip_cannot_reveal_protected_data(): void
    {
        $user = $this->userWithRole('user');
        $trip = $user->trips()->create([
            'token' => Str::random(40),
            'kind' => 'trip',
            'destination' => 'Secret Home',
            'expires_at' => now()->subMinute(), // expired!
            'last_lat' => 23.7500,
            'last_lng' => 90.3800,
        ]);

        // Public tracking endpoint must return 410 Gone for expired trip
        $this->get("/track/{$trip->token}")->assertStatus(410);

        // Polling JSON data endpoint must return 410 Gone and withhold GPS points
        $res = $this->getJson("/track/{$trip->token}/data");
        $res->assertStatus(410)->assertJson(['active' => false]);
    }

    public function test_rate_limiting_protects_sensitive_endpoints(): void
    {
        $user = $this->userWithRole('user');

        // SOS trigger is rate-limited to 6 per minute
        for ($i = 0; $i < 6; $i++) {
            $this->actingAs($user)->post('/sos', ['lat' => 23.7, 'lng' => 90.4]);
        }

        // 7th request in the same minute must be throttled with 429
        $this->actingAs($user)->post('/sos', ['lat' => 23.7, 'lng' => 90.4])->assertStatus(429);
    }
}
