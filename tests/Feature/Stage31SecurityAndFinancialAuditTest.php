<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventBooking;
use App\Models\EventTicketTier;
use App\Models\PaymentEvent;
use App\Models\Trip;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Stage31SecurityAndFinancialAuditTest extends TestCase
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
            'name' => 'National Theatre',
            'category' => 'event',
            'area' => 'Segunbagicha',
            'lat' => 23.7310,
            'lng' => 90.4070,
            'status' => 'approved',
            'lighting' => 4,
            'has_cctv' => true,
            'has_guard' => true,
        ]);

        $event = Event::create([
            'venue_id' => $venue->id,
            'organizer_id' => $organizer->id,
            'title' => 'Dhaka Folk Night 2026',
            'slug' => 'dhaka-folk-night-'.Str::random(6),
            'description' => 'Folk music festival with full security protocols.',
            'category' => 'concert',
            'area' => 'Segunbagicha',
            'address' => 'Segunbagicha, Dhaka',
            'lat' => 23.7310,
            'lng' => 90.4070,
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHours(4),
            'status' => 'published',
        ]);

        $tier = EventTicketTier::create([
            'event_id' => $event->id,
            'name' => 'Regular Entry',
            'price' => $price,
            'capacity' => $capacity,
            'booked_count' => $booked,
            'max_per_user' => 5,
        ]);

        return [$venue, $event, $tier];
    }

    /* =========================================================================
     * 1. Trip Tracking Security & Coordinates Audit
     * ========================================================================= */

    public function test_verify_pin_on_inactive_or_expired_trip_returns_410(): void
    {
        $user = $this->userWithRole('user');
        $trip = $user->trips()->create([
            'token' => Str::random(40),
            'kind' => 'trip',
            'destination' => 'Mohakhali',
            'expires_at' => now()->subMinute(), // expired!
            'share_pin' => '1234',
            'view_count' => 0,
            'last_lat' => 23.7700,
            'last_lng' => 90.4000,
        ]);

        // Attempting to post PIN to an expired trip returns 410 Gone
        $res = $this->post('/track/'.$trip->token.'/pin', ['pin' => '1234']);
        $res->assertStatus(410);
    }

    public function test_pin_protected_trip_coordinates_cannot_be_leaked_without_verification(): void
    {
        $user = $this->userWithRole('user');
        $trip = $user->trips()->create([
            'token' => Str::random(40),
            'kind' => 'trip',
            'destination' => 'Private Destination',
            'expires_at' => now()->addHour(),
            'share_pin' => '4321',
            'view_count' => 0,
            'last_lat' => 23.7711,
            'last_lng' => 90.4022,
        ]);
        $trip->pings()->create(['lat' => 23.7711, 'lng' => 90.4022, 'created_at' => now()]);

        // HTML view only renders PIN prompt; does not leak GPS points
        $htmlRes = $this->get('/track/'.$trip->token);
        $htmlRes->assertOk()->assertViewIs('trips.pin');
        $htmlRes->assertDontSee('23.7711');
        $htmlRes->assertDontSee('90.4022');

        // Telemetry endpoint returns 403 Forbidden without PIN
        $jsonRes = $this->getJson('/track/'.$trip->token.'/data');
        $jsonRes->assertStatus(403)->assertJson(['active' => false, 'message' => 'PIN required.']);
        $jsonRes->assertJsonMissing(['lat' => 23.7711, 'lng' => 90.4022]);
    }

    public function test_max_views_cannot_be_bypassed_by_clearing_cookies_once_exhausted(): void
    {
        $user = $this->userWithRole('user');
        $trip = $user->trips()->create([
            'token' => Str::random(40),
            'kind' => 'trip',
            'destination' => 'Airport',
            'expires_at' => now()->addHour(),
            'max_views' => 2,
            'view_count' => 0,
            'last_lat' => 23.8500,
            'last_lng' => 90.4000,
        ]);

        // First viewer
        $this->get('/track/'.$trip->token)->assertOk();
        $this->assertSame(1, $trip->fresh()->view_count);

        // Second viewer
        $this->flushSession();
        $this->get('/track/'.$trip->token)->assertOk();
        $this->assertSame(2, $trip->fresh()->view_count);

        // Third attempt with fresh session (or cleared cookies) is strictly rejected with 410
        $this->flushSession();
        $resThird = $this->get('/track/'.$trip->token);
        $resThird->assertStatus(410);

        // Direct API call is also rejected with 410
        $this->flushSession();
        $resApi = $this->getJson('/track/'.$trip->token.'/data');
        $resApi->assertStatus(410)->assertJson(['active' => false]);

        // View count never exceeds max_views
        $this->assertSame(2, $trip->fresh()->view_count);
    }

    /* =========================================================================
     * 2. Event Organizer Authorization & Ownership Boundaries
     * ========================================================================= */

    public function test_event_organizer_cannot_approve_own_submitted_event(): void
    {
        $organizer = $this->userWithRole('event_organizer');
        [$venue] = $this->createEventWithTier($organizer);

        // Organizer submits an event
        $submitRes = $this->actingAs($organizer)->post(route('partner.events.store'), [
            'title' => 'Independent Rock Fest',
            'category' => 'concert',
            'venue_id' => $venue->id,
            'address' => 'Segunbagicha',
            'starts_at' => now()->addDays(3)->toDateTimeString(),
            'ends_at' => now()->addDays(3)->addHours(4)->toDateTimeString(),
            'description' => 'Rock concert submission.',
        ]);
        $submitRes->assertRedirect();

        $event = Event::where('title', 'Independent Rock Fest')->first();
        $this->assertNotNull($event);
        $this->assertSame('draft', $event->status);
        $this->assertSame('pending', $event->submission_status);

        // Organizer attempts to call admin approve endpoint
        $approveRes = $this->actingAs($organizer)->post(route('admin.events.approve', $event->id));
        $approveRes->assertForbidden();

        // Event remains unapproved draft
        $freshEvent = $event->fresh();
        $this->assertSame('draft', $freshEvent->status);
        $this->assertSame('pending', $freshEvent->submission_status);
    }

    public function test_event_organizer_cannot_modify_user_roles_or_ban_users(): void
    {
        $organizer = $this->userWithRole('event_organizer');
        $victim = $this->userWithRole('user');

        $res = $this->actingAs($organizer)->patch(route('admin.users.update', $victim->id), [
            'role' => 'super_admin',
            'is_banned' => 1,
        ]);
        $res->assertForbidden();

        // Victim remains untouched
        $this->assertTrue($victim->fresh()->hasRole('user'));
        $this->assertFalse($victim->fresh()->is_banned);
    }

    public function test_event_organizer_cannot_view_passes_for_other_organizers_events(): void
    {
        $organizerA = $this->userWithRole('event_organizer');
        $organizerB = $this->userWithRole('event_organizer');
        $customer = $this->userWithRole('user');
        [, $event, $tier] = $this->createEventWithTier($organizerA, 500, 50, 1);

        $booking = EventBooking::create([
            'user_id' => $customer->id,
            'event_id' => $event->id,
            'ticket_tier_id' => $tier->id,
            'quantity' => 1,
            'unit_price' => 500,
            'total_price' => 500,
            'payment_method' => 'mock_bkash',
            'payment_status' => 'paid',
            'status' => 'confirmed',
        ]);

        // Organizer A (owner) can view the pass
        $this->actingAs($organizerA)->get(route('events.bookings.show', $booking->booking_reference))->assertOk();

        // Customer (owner) can view the pass
        $this->actingAs($customer)->get(route('events.bookings.show', $booking->booking_reference))->assertOk();

        // Organizer B (stranger) is forbidden
        $this->actingAs($organizerB)->get(route('events.bookings.show', $booking->booking_reference))->assertForbidden();
    }

    /* =========================================================================
     * 3. Ticket Cancellation & Financial Workflow Integrity
     * ========================================================================= */

    public function test_cancelled_booking_cannot_be_used_for_event_entry(): void
    {
        $organizer = $this->userWithRole('event_organizer');
        $customer = $this->userWithRole('user');
        $admin = $this->userWithRole('super_admin');
        [, $event, $tier] = $this->createEventWithTier($organizer, 500, 50, 2);

        $booking = EventBooking::create([
            'user_id' => $customer->id,
            'event_id' => $event->id,
            'ticket_tier_id' => $tier->id,
            'quantity' => 1,
            'unit_price' => 500,
            'total_price' => 500,
            'payment_method' => 'mock_bkash',
            'payment_status' => 'paid',
            'status' => 'confirmed',
        ]);

        // Customer cancels booking
        $this->actingAs($customer)->post(route('events.bookings.cancel', $booking->id))->assertRedirect();
        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame('refund_pending', $booking->fresh()->payment_status);

        // Gate staff attempts check-in with the cancelled QR code
        $scanRes = $this->actingAs($admin)->postJson(route('admin.events.checkin'), [
            'code' => $booking->qr_token,
        ]);

        $scanRes->assertStatus(422);
        $scanRes->assertJsonFragment(['success' => false]);
        $this->assertStringContainsString('denied', strtolower($scanRes->json('message')));
    }

    public function test_refund_pending_booking_is_strictly_denied_entry(): void
    {
        $organizer = $this->userWithRole('event_organizer');
        $customer = $this->userWithRole('user');
        $admin = $this->userWithRole('super_admin');
        [, $event, $tier] = $this->createEventWithTier($organizer, 500, 50, 2);

        $booking = EventBooking::create([
            'user_id' => $customer->id,
            'event_id' => $event->id,
            'ticket_tier_id' => $tier->id,
            'quantity' => 1,
            'unit_price' => 500,
            'total_price' => 500,
            'payment_method' => 'mock_bkash',
            'payment_status' => 'refund_pending',
            'status' => 'cancelled',
        ]);

        $scanRes = $this->actingAs($admin)->postJson(route('admin.events.checkin'), [
            'code' => $booking->booking_reference,
        ]);

        $scanRes->assertStatus(422);
        $scanRes->assertJsonFragment(['success' => false]);
    }

    public function test_concurrent_checkin_scans_only_grant_entry_once(): void
    {
        $organizer = $this->userWithRole('event_organizer');
        $customer = $this->userWithRole('user');
        $adminA = $this->userWithRole('super_admin');
        $adminB = $this->userWithRole('safety_admin');
        [, $event, $tier] = $this->createEventWithTier($organizer, 500, 50, 1);

        $booking = EventBooking::create([
            'user_id' => $customer->id,
            'event_id' => $event->id,
            'ticket_tier_id' => $tier->id,
            'quantity' => 1,
            'unit_price' => 500,
            'total_price' => 500,
            'payment_method' => 'mock_bkash',
            'payment_status' => 'paid',
            'status' => 'confirmed',
        ]);

        // First scan succeeds
        $res1 = $this->actingAs($adminA)->postJson(route('admin.events.checkin'), [
            'code' => $booking->qr_token,
        ]);
        $res1->assertOk()->assertJsonFragment(['success' => true]);

        // Second scan is immediately rejected with 409 Conflict
        $res2 = $this->actingAs($adminB)->postJson(route('admin.events.checkin'), [
            'code' => $booking->qr_token,
        ]);
        $res2->assertStatus(409)->assertJsonFragment(['success' => false]);
        $this->assertStringContainsString('Already Checked In', $res2->json('message'));
    }

    public function test_cancellation_does_not_falsely_claim_completed_gateway_refund(): void
    {
        $organizer = $this->userWithRole('event_organizer');
        $customer = $this->userWithRole('user');
        [, $event, $tier] = $this->createEventWithTier($organizer, 750, 20, 2);

        $booking = EventBooking::create([
            'user_id' => $customer->id,
            'event_id' => $event->id,
            'ticket_tier_id' => $tier->id,
            'quantity' => 2,
            'unit_price' => 750,
            'total_price' => 1500,
            'payment_method' => 'mock_bkash',
            'payment_status' => 'paid',
            'status' => 'confirmed',
        ]);

        $this->actingAs($customer)->post(route('events.bookings.cancel', $booking->id))->assertRedirect();

        $fresh = $booking->fresh();
        // Payment status must be refund_pending, NOT refunded
        $this->assertSame('refund_pending', $fresh->payment_status);
        $this->assertNotSame('refunded', $fresh->payment_status);

        // Payment event recorded must be refund_requested, NOT refund_completed
        $event = PaymentEvent::where('event_booking_id', $booking->id)->first();
        $this->assertNotNull($event);
        $this->assertSame('payment.refund_requested', $event->event_type);
        $this->assertSame(1500.0, (float) $event->amount);
    }
}
