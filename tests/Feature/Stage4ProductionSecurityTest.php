<?php

namespace Tests\Feature;

use App\Models\EmergencyContact;
use App\Models\Event;
use App\Models\EventBooking;
use App\Models\EventTicketTier;
use App\Models\PaymentEvent;
use App\Models\Report;
use App\Models\ReportPhoto;
use App\Models\User;
use App\Models\Venue;
use App\Services\IncidentPhotoService;
use App\Services\ReportAnalyzer;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Stage4ProductionSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'nightlife.demo_enabled' => true,
            'nightlife.payments_driver' => 'mock',
        ]);
        $this->seed(RoleSeeder::class);
    }

    private function createEventWithTier(User $organizer, float $price = 500, int $capacity = 50, int $booked = 5): array
    {
        $venue = Venue::create([
            'owner_id' => $organizer->id,
            'name' => 'Security Audit Arena',
            'category' => 'event',
            'area' => 'Gulshan',
            'lat' => 23.7925,
            'lng' => 90.4078,
            'status' => 'approved',
            'lighting' => 4,
            'has_cctv' => true,
            'has_guard' => true,
        ]);

        $event = Event::create([
            'venue_id' => $venue->id,
            'organizer_id' => $organizer->id,
            'title' => 'Security Symphony 2026',
            'slug' => 'security-symphony-'.Str::random(6),
            'description' => 'Security testing concert event.',
            'category' => 'concert',
            'area' => 'Gulshan',
            'address' => 'Gulshan 2, Dhaka',
            'lat' => 23.7925,
            'lng' => 90.4078,
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHours(4),
            'status' => 'published',
        ]);

        $tier = EventTicketTier::create([
            'event_id' => $event->id,
            'name' => 'Secure Tier',
            'price' => $price,
            'capacity' => $capacity,
            'booked_count' => $booked,
            'max_per_user' => 5,
        ]);

        return [$venue, $event, $tier];
    }

    /* =========================================================================
     * 1. Unauthorized Access & IDOR Protections
     * ========================================================================= */

    public function test_user_cannot_view_another_users_digital_pass_or_delete_contacts(): void
    {
        $organizer = $this->userWithRole('event_organizer');
        $victim = $this->userWithRole('user');
        $attacker = $this->userWithRole('user');
        [, $event, $tier] = $this->createEventWithTier($organizer, 500, 50, 1);

        $booking = EventBooking::create([
            'user_id' => $victim->id,
            'event_id' => $event->id,
            'ticket_tier_id' => $tier->id,
            'quantity' => 1,
            'unit_price' => 500,
            'total_price' => 500,
            'payment_method' => 'mock_bkash',
            'payment_status' => 'paid',
            'status' => 'confirmed',
        ]);

        $contact = $victim->emergencyContacts()->create([
            'name' => 'Victim Parent',
            'phone' => '01711223344',
            'relation' => 'Parent',
        ]);

        // Attacker attempts to view victim's ticket pass
        $this->actingAs($attacker)
            ->get(route('events.bookings.show', $booking->booking_reference))
            ->assertForbidden();

        // Attacker attempts to delete victim's emergency contact
        $this->actingAs($attacker)
            ->delete(route('contacts.destroy', $contact->id))
            ->assertForbidden();

        $this->assertDatabaseHas('emergency_contacts', ['id' => $contact->id]);
    }

    public function test_unauthenticated_requests_to_protected_routes_are_redirected_to_login(): void
    {
        $this->get('/trips')->assertRedirect('/login');
        $this->get('/my-bookings')->assertRedirect('/login');
        $this->get('/profile')->assertRedirect('/login');
        $this->get('/contacts')->assertRedirect('/login');
        $this->get('/my-reports')->assertRedirect('/login');
    }

    /* =========================================================================
     * 2. Role Escalation Prevention
     * ========================================================================= */

    public function test_regular_user_and_event_organizer_cannot_escalate_roles(): void
    {
        $user = $this->userWithRole('user');
        $organizer = $this->userWithRole('event_organizer');

        // Regular user attempts role escalation
        $this->actingAs($user)
            ->patch(route('admin.users.update', $user->id), ['role' => 'super_admin'])
            ->assertForbidden();

        // Event organizer attempts role escalation
        $this->actingAs($organizer)
            ->patch(route('admin.users.update', $organizer->id), ['role' => 'super_admin'])
            ->assertForbidden();

        // Both roles remain unchanged
        $this->assertTrue($user->fresh()->hasRole('user'));
        $this->assertTrue($organizer->fresh()->hasRole('event_organizer'));
    }

    /* =========================================================================
     * 3. Invalid Uploads & Binary MIME Spoofing
     * ========================================================================= */

    public function test_photo_service_rejects_fake_image_file_with_png_extension(): void
    {
        /** @var IncidentPhotoService $service */
        $service = app(IncidentPhotoService::class);

        // Create a text file disguised with a .png extension
        $fakeFile = UploadedFile::fake()->createWithContent('exploit.png', '<?php echo "malicious code"; ?>');

        $this->expectException(ValidationException::class);
        $service->validateFile($fakeFile);
    }

    /* =========================================================================
     * 4. Rate Limiting Protections
     * ========================================================================= */

    public function test_forgot_password_endpoint_is_rate_limited(): void
    {
        // 6 attempts within 1 minute allowed by throttle:6,1; the 7th must be throttled with 429
        for ($i = 0; $i < 6; $i++) {
            $this->post('/forgot-password', ['email' => 'test@example.com']);
        }

        $throttledRes = $this->post('/forgot-password', ['email' => 'test@example.com']);
        $throttledRes->assertStatus(429);
    }

    /* =========================================================================
     * 5. Payment Callback Replay & Duplicate Processing Protections
     * ========================================================================= */

    public function test_webhook_replay_for_already_paid_booking_does_not_create_duplicate_events(): void
    {
        $organizer = $this->userWithRole('event_organizer');
        $customer = $this->userWithRole('user');
        [, $event, $tier] = $this->createEventWithTier($organizer, 500, 50, 1);

        $booking = EventBooking::create([
            'user_id' => $customer->id,
            'event_id' => $event->id,
            'ticket_tier_id' => $tier->id,
            'quantity' => 1,
            'unit_price' => 500,
            'total_price' => 500,
            'payment_method' => 'mock_bkash',
            'payment_status' => 'pending',
            'status' => 'pending_payment',
            'provider_reference' => 'MOCK-TEST-REPLAY-1',
        ]);

        $signature = hash_hmac('sha256', (string) $booking->provider_reference, (string) config('app.key'));

        // First webhook confirms the booking
        $res1 = $this->withHeader('X-NightLifeBD-Mock-Signature', $signature)
            ->postJson(route('payments.mock.webhook'), [
                'provider_reference' => $booking->provider_reference,
                'amount' => 500,
            ]);
        $res1->assertOk();
        $this->assertSame('paid', $booking->fresh()->payment_status);

        $eventCountAfterFirst = PaymentEvent::where('event_booking_id', $booking->id)->count();
        $this->assertSame(1, $eventCountAfterFirst);

        // Second (replayed) webhook call
        $res2 = $this->withHeader('X-NightLifeBD-Mock-Signature', $signature)
            ->postJson(route('payments.mock.webhook'), [
                'provider_reference' => $booking->provider_reference,
                'amount' => 500,
            ]);
        $res2->assertOk();

        // Idempotency check: duplicate event was NOT created
        $this->assertSame(1, PaymentEvent::where('event_booking_id', $booking->id)->count());
    }

    public function test_webhook_cannot_revive_expired_or_cancelled_booking(): void
    {
        $organizer = $this->userWithRole('event_organizer');
        $customer = $this->userWithRole('user');
        [, $event, $tier] = $this->createEventWithTier($organizer, 500, 50, 1);

        $booking = EventBooking::create([
            'user_id' => $customer->id,
            'event_id' => $event->id,
            'ticket_tier_id' => $tier->id,
            'quantity' => 1,
            'unit_price' => 500,
            'total_price' => 500,
            'payment_method' => 'mock_bkash',
            'payment_status' => 'cancelled',
            'status' => 'cancelled',
            'provider_reference' => 'MOCK-TEST-CANCELLED-1',
        ]);

        $signature = hash_hmac('sha256', (string) $booking->provider_reference, (string) config('app.key'));

        // Delayed webhook arrives after cancellation
        $res = $this->withHeader('X-NightLifeBD-Mock-Signature', $signature)
            ->postJson(route('payments.mock.webhook'), [
                'provider_reference' => $booking->provider_reference,
                'amount' => 500,
            ]);

        $res->assertStatus(422);
        $res->assertJsonFragment(['status' => 'rejected']);

        // Booking status must NOT be revived to confirmed
        $fresh = $booking->fresh();
        $this->assertSame('cancelled', $fresh->status);
        $this->assertSame('cancelled', $fresh->payment_status);

        // Security event recorded
        $this->assertDatabaseHas('payment_events', [
            'event_booking_id' => $booking->id,
            'event_type' => 'payment.rejected_late',
        ]);
    }

    public function test_webhook_rejects_tampered_amount(): void
    {
        $organizer = $this->userWithRole('event_organizer');
        $customer = $this->userWithRole('user');
        [, $event, $tier] = $this->createEventWithTier($organizer, 1000, 50, 1);

        $booking = EventBooking::create([
            'user_id' => $customer->id,
            'event_id' => $event->id,
            'ticket_tier_id' => $tier->id,
            'quantity' => 1,
            'unit_price' => 1000,
            'total_price' => 1000,
            'payment_method' => 'mock_bkash',
            'payment_status' => 'pending',
            'status' => 'pending_payment',
            'provider_reference' => 'MOCK-TEST-TAMPER-1',
        ]);

        $signature = hash_hmac('sha256', (string) $booking->provider_reference, (string) config('app.key'));

        // Attacker attempts to pay 100 BDT instead of 1000 BDT
        $res = $this->withHeader('X-NightLifeBD-Mock-Signature', $signature)
            ->postJson(route('payments.mock.webhook'), [
                'provider_reference' => $booking->provider_reference,
                'amount' => 100,
            ]);

        $res->assertStatus(422);
        $res->assertJsonFragment(['status' => 'rejected', 'message' => 'Payment amount mismatch']);

        $this->assertSame('pending', $booking->fresh()->payment_status);
        $this->assertDatabaseHas('payment_events', [
            'event_booking_id' => $booking->id,
            'event_type' => 'payment.amount_mismatch',
        ]);
    }

    public function test_webhook_rejects_unverified_signature(): void
    {
        $organizer = $this->userWithRole('event_organizer');
        $customer = $this->userWithRole('user');
        [, $event, $tier] = $this->createEventWithTier($organizer, 500, 50, 1);

        $booking = EventBooking::create([
            'user_id' => $customer->id,
            'event_id' => $event->id,
            'ticket_tier_id' => $tier->id,
            'quantity' => 1,
            'unit_price' => 500,
            'total_price' => 500,
            'payment_method' => 'mock_bkash',
            'payment_status' => 'pending',
            'status' => 'pending_payment',
            'provider_reference' => 'MOCK-TEST-FORGED-1',
        ]);

        // Forged signature
        $res = $this->withHeader('X-NightLifeBD-Mock-Signature', 'forged_invalid_signature')
            ->postJson(route('payments.mock.webhook'), [
                'provider_reference' => $booking->provider_reference,
            ]);

        $res->assertStatus(422);
        $res->assertJsonFragment(['status' => 'rejected', 'message' => 'Signature not verified']);

        $this->assertSame('pending', $booking->fresh()->payment_status);
        $this->assertDatabaseHas('payment_events', [
            'event_booking_id' => $booking->id,
            'signature_status' => 'unverified',
        ]);
    }

    /* =========================================================================
     * 6. Inventory Race Conditions & Account Deletion Safety
     * ========================================================================= */

    public function test_account_deletion_safely_releases_held_seats_for_upcoming_events(): void
    {
        $organizer = $this->userWithRole('event_organizer');
        $customer = $this->userWithRole('user');
        [, $event, $tier] = $this->createEventWithTier($organizer, 500, 50, 5);

        EventBooking::create([
            'user_id' => $customer->id,
            'event_id' => $event->id,
            'ticket_tier_id' => $tier->id,
            'quantity' => 2,
            'unit_price' => 500,
            'total_price' => 1000,
            'payment_method' => 'mock_bkash',
            'payment_status' => 'paid',
            'status' => 'confirmed',
        ]);

        // User deletes account
        $this->actingAs($customer)
            ->delete('/profile', ['password' => 'password'])
            ->assertRedirect('/');

        // Tier capacity must be released (5 - 2 = 3) so seats aren't permanently stranded
        $this->assertSame(3, $tier->fresh()->booked_count);
    }

    /* =========================================================================
     * 7. Private File Access & Sensitive Data Protection
     * ========================================================================= */

    public function test_unverified_report_photos_are_strictly_inaccessible_to_unauthorized_users(): void
    {
        $author = $this->userWithRole('user');
        $stranger = $this->userWithRole('user');

        $report = Report::create([
            'user_id' => $author->id,
            'kind' => 'incident',
            'body' => 'Private incident report with confidential photo.',
            'incident_time' => now(),
            'status' => 'pending', // unverified!
        ]);

        Storage::disk(IncidentPhotoService::DISK)->put('photos/confidential.jpg', 'fake_photo_bytes');
        Storage::disk(IncidentPhotoService::DISK)->put('thumbs/confidential_thumb.jpg', 'fake_thumb_bytes');

        $photo = ReportPhoto::create([
            'report_id' => $report->id,
            'path' => 'photos/confidential.jpg',
            'thumbnail' => 'thumbs/confidential_thumb.jpg',
            'size_bytes' => 100,
        ]);

        // Stranger attempting to view full photo is forbidden
        $this->actingAs($stranger)
            ->get(route('reports.photos.show', [$photo->id, 'full']))
            ->assertForbidden();

        // Stranger attempting to view thumbnail is forbidden
        $this->actingAs($stranger)
            ->get(route('reports.photos.show', [$photo->id, 'thumb']))
            ->assertForbidden();

        // Author CAN view their own photo
        $this->actingAs($author)
            ->get(route('reports.photos.show', [$photo->id, 'full']))
            ->assertOk();
    }

    public function test_password_reset_failure_does_not_reveal_internal_server_paths(): void
    {
        // When delivery fails, error message must not expose server logs path
        config(['mail.mailers.smtp.host' => 'invalid.unreachable.host']);

        $res = $this->post('/forgot-password', ['email' => 'validuser@example.com']);
        $res->assertSessionHasErrors('email');

        $errors = session('errors')->get('email');
        $errorMessage = implode(' ', $errors);

        $this->assertStringNotContainsString('storage/logs', $errorMessage);
        $this->assertStringNotContainsString('SMTP credentials', $errorMessage);
    }

    /* =========================================================================
     * 8. AI Reliability & Deterministic Fallback
     * ========================================================================= */

    public function test_report_analyzer_falls_back_deterministically_when_gemini_fails(): void
    {
        /** @var ReportAnalyzer $analyzer */
        $analyzer = app(ReportAnalyzer::class);
        $user = $this->userWithRole('user');

        // Gemini key set to null (simulating API failure / offline)
        config(['services.gemini.key' => null]);

        $report = Report::create([
            'user_id' => $user->id,
            'kind' => 'incident',
            'body' => 'Mirpur 10 e bag chintai hoyeche rastay kono bati chilo na.',
            'incident_time' => now(),
            'status' => 'pending',
        ]);

        $analyzer->analyze($report);

        $fresh = $report->fresh();
        // Deterministic local keyword analyzer categorized the incident without crashing
        $this->assertSame('theft', $fresh->category);
        $this->assertSame('keyword', $fresh->analysis_source);
        // AI never automatically verifies report
        $this->assertSame('pending', $fresh->status);
    }
}
