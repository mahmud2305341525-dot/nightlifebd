<?php

namespace Tests\Feature;

use App\Models\SafeHaven;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimerAndSosTest extends TestCase
{
    use RefreshDatabase;

    public function test_overdue_timer_alerts_contacts_once(): void
    {
        $user = $this->userWithRole('user');
        $user->emergencyContacts()->create(['name' => 'Mom', 'phone' => '01712345678', 'email' => 'mom@example.com']);
        $timer = $user->safetyTimers()->create(['due_at' => now()->subMinutes(2)]);

        $this->artisan('safety:check-timers')->assertSuccessful();
        $this->assertNotNull($timer->fresh()->alerted_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'timer.alert']);

        $first = $timer->fresh()->alerted_at;
        $this->artisan('safety:check-timers')->assertSuccessful();
        $this->assertEquals($first, $timer->fresh()->alerted_at);
    }

    public function test_confirmed_timer_is_not_alerted(): void
    {
        $user = $this->userWithRole('user');
        $timer = $user->safetyTimers()->create(['due_at' => now()->subMinutes(2), 'confirmed_at' => now()]);
        $this->artisan('safety:check-timers')->assertSuccessful();
        $this->assertNull($timer->fresh()->alerted_at);
    }

    public function test_sos_writes_audit_log_and_returns_counts(): void
    {
        $user = $this->userWithRole('user');
        $user->emergencyContacts()->create(['name' => 'Mom', 'phone' => '01712345678', 'email' => 'mom@example.com']);
        $this->actingAs($user)->postJson('/sos', ['lat' => 23.78, 'lng' => 90.4])->assertOk()->assertJson(['ok' => true, 'contacts' => 1]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sos.triggered', 'user_id' => $user->id]);
    }

    public function test_nearest_help_points_sorted_by_distance(): void
    {
        SafeHaven::create(['type' => 'police', 'name' => 'Far', 'lat' => 23.90, 'lng' => 90.40]);
        SafeHaven::create(['type' => 'police', 'name' => 'Near', 'lat' => 23.781, 'lng' => 90.401]);
        $res = $this->getJson('/api/nearest?lat=23.78&lng=90.40')->assertOk();
        $this->assertSame('Near', $res->json('0.name'));
        $this->assertGreaterThanOrEqual(1, $res->json('0.eta_min'));
    }

    public function test_user_cannot_create_multiple_simultaneous_active_safety_timers(): void
    {
        $user = $this->userWithRole('user');
        $user->emergencyContacts()->create(['name' => 'Dad', 'phone' => '01712345678']);

        // First timer starts successfully
        $this->actingAs($user)->post('/timer', [
            'due_at' => now()->addMinutes(30)->toDateTimeString(),
            'note' => 'Walking home from campus',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('safety_timers', 1);

        // Attempting to start a second active timer while first is running is rejected
        $this->actingAs($user)->post('/timer', [
            'due_at' => now()->addMinutes(45)->toDateTimeString(),
            'note' => 'Second timer attempt',
        ])->assertSessionHasErrors('due_at');

        $this->assertDatabaseCount('safety_timers', 1);

        // Confirming the running timer allows starting a new one
        $timer = $user->safetyTimers()->first();
        $this->actingAs($user)->post("/timer/{$timer->id}/confirm")->assertSessionHasNoErrors();
        $this->assertNotNull($timer->fresh()->confirmed_at);

        $this->actingAs($user)->post('/timer', [
            'due_at' => now()->addMinutes(45)->toDateTimeString(),
            'note' => 'New timer after confirmation',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('safety_timers', 2);
    }

    public function test_sos_page_supplies_and_displays_emergency_contacts(): void
    {
        $user = $this->userWithRole('user');
        $user->emergencyContacts()->create([
            'name' => 'Dr. Rafiq',
            'phone' => '01712345678',
            'relation' => 'Doctor',
        ]);

        $other = $this->userWithRole('user');
        $other->emergencyContacts()->create([
            'name' => 'Stranger Danger',
            'phone' => '01899999999',
            'relation' => 'None',
        ]);

        $res = $this->actingAs($user)->get('/sos');
        $res->assertOk();
        $res->assertViewHas('contacts');
        $res->assertSee('Dr. Rafiq');
        $res->assertSee('01712345678');
        $res->assertDontSee('Stranger Danger');
    }
}
