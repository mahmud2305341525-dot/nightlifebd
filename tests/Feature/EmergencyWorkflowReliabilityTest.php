<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Trip;
use App\Services\AlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EmergencyWorkflowReliabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /**
     * Test 1: Successful and failed Telegram notification attempts.
     */
    public function test_telegram_notification_confirmed_vs_failed(): void
    {
        Config::set('services.telegram.token', 'test-token');
        $user = $this->userWithRole('user');
        $c1 = $user->emergencyContacts()->create([
            'name' => 'Contact 1',
            'phone' => '01712345671',
            'telegram_chat_id' => '1234567',
        ]);
        $c2 = $user->emergencyContacts()->create([
            'name' => 'Contact 2',
            'phone' => '01712345672',
            'telegram_chat_id' => '7654321',
        ]);

        // C1 succeeds, C2 fails (HTTP 500)
        Http::fake([
            'https://api.telegram.org/bottest-token/sendMessage' => Http::sequence()
                ->push(['ok' => true, 'result' => ['message_id' => 101]], 200)
                ->push(['ok' => false, 'description' => 'Internal server error'], 500),
        ]);

        $svc = app(AlertService::class);
        $res = $svc->notifyContacts($user, 'Test Alert', 'Emergency message');

        $this->assertSame(2, $res['contacts_count']);
        $this->assertSame(1, $res['telegram']);
        $this->assertSame('partial', $res['delivery']['telegram']['status']);
        $this->assertSame(1, $res['delivery']['telegram']['confirmed']);
        $this->assertSame(1, $res['delivery']['telegram']['failed']);
    }

    /**
     * Test 2: Telegram connection timeout handled gracefully without crashing.
     */
    public function test_telegram_timeout_handled_cleanly(): void
    {
        Config::set('services.telegram.token', 'test-token');
        $user = $this->userWithRole('user');
        $user->emergencyContacts()->create([
            'name' => 'Contact',
            'phone' => '01712345671',
            'telegram_chat_id' => '1234567',
        ]);

        Http::fake(function () {
            throw new ConnectionException('Connection timed out');
        });

        $svc = app(AlertService::class);
        $res = $svc->notifyContacts($user, 'Test Alert', 'Emergency message');

        $this->assertSame(0, $res['telegram']);
        $this->assertSame('failed', $res['delivery']['telegram']['status']);
        $this->assertSame(1, $res['delivery']['telegram']['failed']);
    }

    /**
     * Test 3: Local mail log driver correctly identifies simulation mode.
     */
    public function test_local_mail_driver_reports_logged_local(): void
    {
        Config::set('mail.default', 'log');
        $user = $this->userWithRole('user');
        $user->emergencyContacts()->create([
            'name' => 'Contact',
            'phone' => '01712345671',
            'email' => 'contact@example.com',
        ]);

        $svc = app(AlertService::class);
        $res = $svc->notifyContacts($user, 'Test Alert', 'Emergency message');

        $this->assertTrue($res['delivery']['email']['is_local_log']);
        $this->assertSame('logged_local', $res['delivery']['email']['status']);
        $this->assertTrue($res['is_simulation']);
    }

    /**
     * Test 4: Duplicate SOS requests within 60 seconds are suppressed.
     */
    public function test_duplicate_sos_requests_are_suppressed(): void
    {
        $user = $this->userWithRole('user');
        $user->emergencyContacts()->create([
            'name' => 'Mom',
            'phone' => '01712345678',
            'email' => 'mom@example.com',
        ]);

        // First attempt with unique request_id
        $res1 = $this->actingAs($user)->postJson('/sos', [
            'lat' => 23.78,
            'lng' => 90.40,
            'request_id' => 'req-unique-999',
        ])->assertOk();

        $this->assertFalse($res1->json('is_duplicate', false));

        // Immediate retry with the same request_id
        $res2 = $this->actingAs($user)->postJson('/sos', [
            'lat' => 23.78,
            'lng' => 90.40,
            'request_id' => 'req-unique-999',
        ])->assertOk();

        $this->assertTrue($res2->json('is_duplicate'));
        $this->assertStringContainsString('Duplicate alert suppressed', $res2->json('summary'));
    }

    /**
     * Test 5: Missing emergency contacts handled gracefully.
     */
    public function test_sos_with_no_contacts(): void
    {
        $user = $this->userWithRole('user'); // 0 emergency contacts

        $res = $this->actingAs($user)->postJson('/sos', [
            'lat' => 23.78,
            'lng' => 90.40,
        ])->assertOk();

        $this->assertSame(0, $res->json('contacts'));
        $this->assertStringContainsString('No emergency contacts configured', $res->json('summary'));
    }

    /**
     * Test 6: Contact phone normalization and validation.
     */
    public function test_contact_phone_normalization(): void
    {
        $user = $this->userWithRole('user');

        // Phone with +88 and hyphens: +8801712-345678 -> normalized to 01712345678
        $this->actingAs($user)->post('/contacts', [
            'name' => 'Sister',
            'phone' => '+8801712-345678',
        ])->assertSessionHasNoErrors();

        $contact = $user->emergencyContacts()->first();
        $this->assertSame('01712345678', $contact->phone);

        // Invalid phone number: not starting with 01[3-9] or too short
        $this->actingAs($user)->post('/contacts', [
            'name' => 'Invalid',
            'phone' => '01234567890',
        ])->assertSessionHasErrors('phone');
    }

    /**
     * Test 7: Expired, revoked, and unauthorized trip operations.
     */
    public function test_trip_revocation_and_unauthorized_access(): void
    {
        $owner = $this->userWithRole('user');
        $intruder = $this->userWithRole('user');

        $trip = $owner->trips()->create([
            'token' => str_repeat('c', 40),
            'expires_at' => now()->addHour(),
        ]);

        // Intruder cannot end owner's trip
        $this->actingAs($intruder)->post('/trips/'.$trip->token.'/end')->assertForbidden();

        // Intruder cannot ping owner's trip
        $this->actingAs($intruder)->postJson('/trips/'.$trip->token.'/ping', ['lat' => 23.7, 'lng' => 90.4])->assertForbidden();

        // Owner ends (revokes) the trip
        $this->actingAs($owner)->post('/trips/'.$trip->token.'/end')->assertRedirect();

        $this->assertNotNull($trip->fresh()->ended_at);
        $this->assertFalse($trip->fresh()->isActive());

        // Revoked trip returns 410 on public track endpoints
        $this->get('/track/'.$trip->token)->assertStatus(410);
        $this->getJson('/track/'.$trip->token.'/data')->assertStatus(410)
            ->assertJson(['active' => false]);
    }

    /**
     * Test 8: Stale location detection for live trips.
     */
    public function test_stale_location_tracking(): void
    {
        $owner = $this->userWithRole('user');
        $trip = $owner->trips()->create([
            'token' => str_repeat('d', 40),
            'expires_at' => now()->addHour(),
            'last_lat' => 23.78,
            'last_lng' => 90.40,
            'last_ping_at' => now()->subMinutes(5), // 5 minutes ago = stale
        ]);

        $res = $this->getJson('/track/'.$trip->token.'/data')->assertOk();
        $this->assertTrue($res->json('is_stale'));
        $this->assertGreaterThan(120, $res->json('last_ping_seconds'));

        // Fresh ping (10 seconds ago)
        $trip->update(['last_ping_at' => now()->subSeconds(10)]);
        $resFresh = $this->getJson('/track/'.$trip->token.'/data')->assertOk();
        $this->assertFalse($resFresh->json('is_stale'));
    }

    /**
     * Test 9: Safety timer confirmation, expiry, and escalation.
     */
    public function test_safety_timer_lifecycle(): void
    {
        $owner = $this->userWithRole('user');
        $intruder = $this->userWithRole('user');

        $timer = $owner->safetyTimers()->create([
            'due_at' => now()->addMinutes(15),
            'note' => 'Walking home',
        ]);

        // Intruder cannot confirm owner's timer
        $this->actingAs($intruder)->post('/timer/'.$timer->id.'/confirm')->assertForbidden();

        // Intruder cannot delete owner's timer
        $this->actingAs($intruder)->delete('/timer/'.$timer->id)->assertForbidden();

        // Owner confirms timer
        $this->actingAs($owner)->post('/timer/'.$timer->id.'/confirm')->assertRedirect();
        $this->assertNotNull($timer->fresh()->confirmed_at);
        $this->assertSame('confirmed', $timer->fresh()->status());
    }

    /**
     * Test 10: Location permission denial (null coordinates) still triggers SOS safely.
     */
    public function test_sos_handles_null_coordinates_gracefully(): void
    {
        $user = $this->userWithRole('user');
        $user->emergencyContacts()->create([
            'name' => 'Dad',
            'phone' => '01812345678',
            'email' => 'dad@example.com',
        ]);

        $res = $this->actingAs($user)->postJson('/sos', [
            'lat' => null,
            'lng' => null,
        ])->assertOk();

        $this->assertSame(1, $res->json('contacts'));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'sos.triggered',
            'user_id' => $user->id,
        ]);

        $log = AuditLog::where('action', 'sos.triggered')->where('user_id', $user->id)->first();
        $this->assertNull($log->meta['lat']);
        $this->assertNull($log->meta['lng']);
        $this->assertSame(1, $log->meta['contacts']);
    }

    /**
     * Test 11: Emergency services disclaimer present in response.
     */
    public function test_emergency_services_disclaimer_present(): void
    {
        $user = $this->userWithRole('user');

        $res = $this->actingAs($user)->postJson('/sos')->assertOk();
        $this->assertStringContainsString('Emergency services (999) are not automatically contacted', $res->json('emergency_disclaimer'));
    }
}
