<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\UserController;
use App\Models\AuditLog;
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

class Stage3AuthorizationAndWorkflowTest extends TestCase
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

    /* =========================================================================
     * Task 1 / SEC-02: Trip Tracking Authorization & Telemetry Views
     * ========================================================================= */

    public function test_authorized_viewer_can_view_trip_and_poll_telemetry_without_exhausting_limit(): void
    {
        $user = $this->userWithRole('user');
        $trip = $user->trips()->create([
            'token' => Str::random(40),
            'kind' => 'trip',
            'destination' => 'Dhanmondi 27',
            'expires_at' => now()->addHour(),
            'max_views' => 2,
            'view_count' => 0,
            'last_lat' => 23.7500,
            'last_lng' => 90.3800,
        ]);
        $trip->pings()->create(['lat' => 23.7500, 'lng' => 90.3800, 'created_at' => now()]);

        // First view consumes 1 view slot
        $res = $this->get('/track/'.$trip->token);
        $res->assertOk();
        $res->assertViewIs('trips.track');
        $this->assertSame(1, $trip->fresh()->view_count);

        // Polling telemetry repeatedly in the same session does not exhaust the remaining view slot
        for ($i = 0; $i < 3; $i++) {
            $dataRes = $this->getJson('/track/'.$trip->token.'/data');
            $dataRes->assertOk();
            $dataRes->assertJson(['active' => true, 'lat' => 23.7500, 'lng' => 90.3800]);
        }

        // View count must remain 1 despite 3 telemetry polls
        $this->assertSame(1, $trip->fresh()->view_count);
    }

    public function test_direct_telemetry_request_without_session_consumes_view_slot(): void
    {
        $user = $this->userWithRole('user');
        $trip = $user->trips()->create([
            'token' => Str::random(40),
            'kind' => 'trip',
            'destination' => 'Gulshan 2',
            'expires_at' => now()->addHour(),
            'max_views' => 2,
            'view_count' => 0,
            'last_lat' => 23.7900,
            'last_lng' => 90.4100,
        ]);

        // Direct request to JSON telemetry without visiting HTML first
        $res = $this->getJson('/track/'.$trip->token.'/data');
        $res->assertOk();
        $res->assertJson(['active' => true]);
        $this->assertSame(1, $trip->fresh()->view_count);

        // Immediate subsequent call within same session does not increment
        $res2 = $this->getJson('/track/'.$trip->token.'/data');
        $res2->assertOk();
        $this->assertSame(1, $trip->fresh()->view_count);
    }

    public function test_telemetry_and_view_access_are_rejected_with_410_when_max_views_reached(): void
    {
        $user = $this->userWithRole('user');
        $trip = $user->trips()->create([
            'token' => Str::random(40),
            'kind' => 'trip',
            'destination' => 'Uttara',
            'expires_at' => now()->addHour(),
            'max_views' => 1,
            'view_count' => 0,
            'last_lat' => 23.8700,
            'last_lng' => 90.3900,
        ]);

        // Viewer 1 views the trip
        $res1 = $this->get('/track/'.$trip->token);
        $res1->assertOk();
        $this->assertSame(1, $trip->fresh()->view_count);

        // Viewer 2 (new session) attempts to view HTML page -> 410 Gone
        $this->flushSession();
        $res2 = $this->get('/track/'.$trip->token);
        $res2->assertStatus(410);

        // Viewer 2 attempts to call JSON telemetry directly -> 410 Gone with informative message
        $this->flushSession();
        $resData = $this->getJson('/track/'.$trip->token.'/data');
        $resData->assertStatus(410);
        $resData->assertJson(['active' => false]);

        // View count never exceeds max_views
        $this->assertSame(1, $trip->fresh()->view_count);
    }

    public function test_concurrent_access_cannot_exceed_max_views(): void
    {
        $user = $this->userWithRole('user');
        $trip = $user->trips()->create([
            'token' => Str::random(40),
            'kind' => 'trip',
            'destination' => 'Dhanmondi',
            'expires_at' => now()->addHour(),
            'max_views' => 2,
            'view_count' => 0,
            'last_lat' => 23.7500,
            'last_lng' => 90.3800,
        ]);

        // Simulate 5 independent clients hitting the endpoint
        $successCount = 0;
        $goneCount = 0;

        for ($i = 0; $i < 5; $i++) {
            $this->flushSession();
            $res = $this->get('/track/'.$trip->token);
            if ($res->status() === 200) {
                $successCount++;
            } elseif ($res->status() === 410) {
                $goneCount++;
            }
        }

        // Exactly 2 succeeded and 3 were rejected with 410
        $this->assertSame(2, $successCount);
        $this->assertSame(3, $goneCount);
        $this->assertSame(2, $trip->fresh()->view_count);
    }

    public function test_trip_share_pin_is_enforced_on_both_page_and_telemetry(): void
    {
        $user = $this->userWithRole('user');
        $trip = $user->trips()->create([
            'token' => Str::random(40),
            'kind' => 'trip',
            'destination' => 'Mohakhali',
            'expires_at' => now()->addHour(),
            'share_pin' => '9876',
            'max_views' => null,
            'view_count' => 0,
            'last_lat' => 23.7700,
            'last_lng' => 90.4000,
        ]);

        // Without PIN, HTML page shows PIN challenge
        $res = $this->get('/track/'.$trip->token);
        $res->assertOk();
        $res->assertViewIs('trips.pin');

        // Without PIN, JSON telemetry returns 403
        $dataRes = $this->getJson('/track/'.$trip->token.'/data');
        $dataRes->assertStatus(403);
        $dataRes->assertJson(['active' => false, 'message' => 'PIN required.']);

        // Submit correct PIN
        $pinRes = $this->post('/track/'.$trip->token.'/pin', ['pin' => '9876']);
        $pinRes->assertRedirect(route('track.show', $trip->token));

        // Now authorized in session, both page and telemetry succeed
        $this->get('/track/'.$trip->token)->assertOk()->assertViewIs('trips.track');
        $this->getJson('/track/'.$trip->token.'/data')->assertOk()->assertJson(['active' => true]);
    }

    public function test_inactive_trip_returns_410_on_page_and_telemetry(): void
    {
        $user = $this->userWithRole('user');
        $trip = $user->trips()->create([
            'token' => Str::random(40),
            'kind' => 'trip',
            'destination' => 'Banani',
            'expires_at' => now()->addHour(),
            'ended_at' => now()->subMinute(),
            'last_lat' => 23.7900,
            'last_lng' => 90.4000,
        ]);

        $this->get('/track/'.$trip->token)->assertStatus(410);
        $this->getJson('/track/'.$trip->token.'/data')->assertStatus(410)->assertJson(['active' => false]);
    }

    /* =========================================================================
     * Task 2 / SEC-03: Event Organizer Role Management & Permission Boundary
     * ========================================================================= */

    public function test_event_organizer_role_is_registered_in_user_controller_roles(): void
    {
        $this->assertContains('event_organizer', UserController::ROLES);
    }

    public function test_super_admin_can_assign_event_organizer_role_to_user(): void
    {
        $admin = $this->userWithRole('super_admin');
        $targetUser = $this->userWithRole('user');

        $res = $this->actingAs($admin)->patch(route('admin.users.update', $targetUser), [
            'role' => 'event_organizer',
            'is_banned' => 0,
        ]);

        $res->assertRedirect();
        $res->assertSessionHas('status', 'User updated.');

        $this->assertTrue($targetUser->fresh()->hasRole('event_organizer'));
        $this->assertFalse($targetUser->fresh()->hasRole('user'));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'user.updated',
        ]);
    }

    public function test_event_organizer_can_access_partner_event_submission(): void
    {
        $organizer = $this->userWithRole('event_organizer');

        $res = $this->actingAs($organizer)->get(route('partner.events.create'));
        $res->assertOk();
    }

    public function test_event_organizer_cannot_access_administrative_moderation_routes(): void
    {
        $organizer = $this->userWithRole('event_organizer');

        // Forbidden from user administration
        $this->actingAs($organizer)->get(route('admin.users'))->assertForbidden();

        // Forbidden from admin dashboard
        $this->actingAs($organizer)->get(route('admin.dashboard'))->assertForbidden();

        // Forbidden from venue moderation
        $this->actingAs($organizer)->get(route('admin.venues'))->assertForbidden();
    }

    /* =========================================================================
     * Task 3 / SEC-04: Paid Ticket Cancellation Integrity & Lifecycle
     * ========================================================================= */

    private function createEventWithTier(User $organizer, float $price = 500, int $capacity = 50, int $booked = 5): array
    {
        $venue = Venue::create([
            'owner_id' => $organizer->id,
            'name' => 'City Concert Hall',
            'category' => 'event',
            'area' => 'Dhanmondi',
            'lat' => 23.7460,
            'lng' => 90.3750,
            'status' => 'approved',
            'lighting' => 4,
            'has_cctv' => true,
            'has_guard' => true,
        ]);

        $event = Event::create([
            'venue_id' => $venue->id,
            'organizer_id' => $organizer->id,
            'title' => 'Dhaka Jazz Night 2026',
            'slug' => 'dhaka-jazz-night-'.Str::random(6),
            'description' => 'Live jazz music and entertainment.',
            'category' => 'concert',
            'area' => 'Dhanmondi',
            'address' => 'Satmasjid Road',
            'lat' => 23.7460,
            'lng' => 90.3750,
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHours(4),
            'status' => 'published',
        ]);

        $tier = EventTicketTier::create([
            'event_id' => $event->id,
            'name' => 'VIP Lounge',
            'price' => $price,
            'capacity' => $capacity,
            'booked_count' => $booked,
            'max_per_user' => 5,
        ]);

        return [$venue, $event, $tier];
    }

    public function test_paid_ticket_cancellation_transitions_to_refund_pending_and_restores_capacity(): void
    {
        $organizer = $this->userWithRole('event_organizer');
        $customer = $this->userWithRole('user');
        [, $event, $tier] = $this->createEventWithTier($organizer, 500, 50, 4);

        $booking = EventBooking::create([
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

        $res = $this->actingAs($customer)->post(route('events.bookings.cancel', $booking->id));
        $res->assertRedirect();
        $res->assertSessionHas('status');

        $freshBooking = $booking->fresh();
        $this->assertSame('cancelled', $freshBooking->status);
        // SEC-04 requirement: transition to refund_pending, NOT 'refunded'
        $this->assertSame('refund_pending', $freshBooking->payment_status);

        // Capacity restored (4 - 2 = 2)
        $this->assertSame(2, $tier->fresh()->booked_count);

        // PaymentEvent created
        $paymentEvent = PaymentEvent::where('event_booking_id', $booking->id)->latest('id')->first();
        $this->assertNotNull($paymentEvent);
        $this->assertSame('payment.refund_requested', $paymentEvent->event_type);
        $this->assertSame(1000.0, (float) $paymentEvent->amount);
        $this->assertSame('mock_bkash', $paymentEvent->provider);

        // AuditLog recorded
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $customer->id,
            'action' => 'booking.cancelled',
        ]);
    }

    public function test_free_ticket_cancellation_transitions_to_cancelled_without_refund(): void
    {
        $organizer = $this->userWithRole('event_organizer');
        $customer = $this->userWithRole('user');
        [, $event, $tier] = $this->createEventWithTier($organizer, 0, 100, 3);

        $booking = EventBooking::create([
            'user_id' => $customer->id,
            'event_id' => $event->id,
            'ticket_tier_id' => $tier->id,
            'quantity' => 1,
            'unit_price' => 0,
            'total_price' => 0,
            'payment_method' => 'free',
            'payment_status' => 'free',
            'status' => 'confirmed',
        ]);

        $res = $this->actingAs($customer)->post(route('events.bookings.cancel', $booking->id));
        $res->assertRedirect();

        $freshBooking = $booking->fresh();
        $this->assertSame('cancelled', $freshBooking->status);
        $this->assertSame('cancelled', $freshBooking->payment_status);

        // Capacity restored (3 - 1 = 2)
        $this->assertSame(2, $tier->fresh()->booked_count);

        // PaymentEvent recorded as payment.cancelled (not refund)
        $paymentEvent = PaymentEvent::where('event_booking_id', $booking->id)->latest('id')->first();
        $this->assertNotNull($paymentEvent);
        $this->assertSame('payment.cancelled', $paymentEvent->event_type);
    }

    public function test_user_cannot_cancel_another_users_booking(): void
    {
        $organizer = $this->userWithRole('event_organizer');
        $owner = $this->userWithRole('user');
        $attacker = $this->userWithRole('user');
        [, $event, $tier] = $this->createEventWithTier($organizer, 500, 50, 2);

        $booking = EventBooking::create([
            'user_id' => $owner->id,
            'event_id' => $event->id,
            'ticket_tier_id' => $tier->id,
            'quantity' => 2,
            'unit_price' => 500,
            'total_price' => 1000,
            'payment_method' => 'mock_bkash',
            'payment_status' => 'paid',
            'status' => 'confirmed',
        ]);

        $res = $this->actingAs($attacker)->post(route('events.bookings.cancel', $booking->id));
        $res->assertForbidden();

        // Booking remains untouched
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertSame('paid', $booking->fresh()->payment_status);
        $this->assertSame(2, $tier->fresh()->booked_count);
    }

    public function test_already_cancelled_booking_cannot_be_cancelled_again(): void
    {
        $organizer = $this->userWithRole('event_organizer');
        $customer = $this->userWithRole('user');
        [, $event, $tier] = $this->createEventWithTier($organizer, 500, 50, 10);

        $booking = EventBooking::create([
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

        // First cancellation succeeds
        $this->actingAs($customer)->post(route('events.bookings.cancel', $booking->id))->assertRedirect();
        $this->assertSame(8, $tier->fresh()->booked_count);

        // Second cancellation attempt is rejected and does not decrement capacity again
        $secondRes = $this->actingAs($customer)->post(route('events.bookings.cancel', $booking->id));
        $secondRes->assertRedirect();
        $secondRes->assertSessionHas('error', 'Booking is already cancelled.');

        // Capacity remains 8 (not decremented to 6)
        $this->assertSame(8, $tier->fresh()->booked_count);
    }

    public function test_checked_in_ticket_cannot_be_cancelled(): void
    {
        $organizer = $this->userWithRole('event_organizer');
        $customer = $this->userWithRole('user');
        [, $event, $tier] = $this->createEventWithTier($organizer, 500, 50, 5);

        $booking = EventBooking::create([
            'user_id' => $customer->id,
            'event_id' => $event->id,
            'ticket_tier_id' => $tier->id,
            'quantity' => 1,
            'unit_price' => 500,
            'total_price' => 500,
            'payment_method' => 'mock_bkash',
            'payment_status' => 'paid',
            'status' => 'checked_in',
            'checked_in_at' => now(),
        ]);

        $res = $this->actingAs($customer)->post(route('events.bookings.cancel', $booking->id));
        $res->assertRedirect();
        $res->assertSessionHas('error', 'Cannot cancel a ticket that has already been checked in.');

        $this->assertSame('checked_in', $booking->fresh()->status);
        $this->assertSame(5, $tier->fresh()->booked_count);
    }

    public function test_past_event_booking_cannot_be_cancelled(): void
    {
        $organizer = $this->userWithRole('event_organizer');
        $customer = $this->userWithRole('user');
        [$venue] = $this->createEventWithTier($organizer);

        $pastEvent = Event::create([
            'venue_id' => $venue->id,
            'organizer_id' => $organizer->id,
            'title' => 'Past Rock Concert',
            'slug' => 'past-rock-concert-'.Str::random(6),
            'description' => 'Past concert event.',
            'category' => 'concert',
            'area' => 'Dhanmondi',
            'address' => 'Satmasjid Road',
            'lat' => 23.7460,
            'lng' => 90.3750,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->subDay()->addHours(3),
            'status' => 'published',
        ]);

        $tier = EventTicketTier::create([
            'event_id' => $pastEvent->id,
            'name' => 'Regular Entry',
            'price' => 300,
            'capacity' => 100,
            'booked_count' => 10,
            'max_per_user' => 5,
        ]);

        $booking = EventBooking::create([
            'user_id' => $customer->id,
            'event_id' => $pastEvent->id,
            'ticket_tier_id' => $tier->id,
            'quantity' => 1,
            'unit_price' => 300,
            'total_price' => 300,
            'payment_method' => 'mock_bkash',
            'payment_status' => 'paid',
            'status' => 'confirmed',
        ]);

        $res = $this->actingAs($customer)->post(route('events.bookings.cancel', $booking->id));
        $res->assertRedirect();
        $res->assertSessionHas('error', 'Cannot cancel ticket for an event that has already started.');

        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertSame(10, $tier->fresh()->booked_count);
    }

    public function test_cancellation_rolls_back_completely_if_error_occurs(): void
    {
        $organizer = $this->userWithRole('event_organizer');
        $customer = $this->userWithRole('user');
        [, $event, $tier] = $this->createEventWithTier($organizer, 500, 50, 4);

        $booking = EventBooking::create([
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

        // Force an exception during transaction via event listener
        PaymentEvent::saving(function () {
            throw new \RuntimeException('Simulated payment event storage failure');
        });

        try {
            $this->actingAs($customer)->post(route('events.bookings.cancel', $booking->id));
        } catch (\RuntimeException $e) {
            // Caught simulated exception
        }

        // Verify transaction rolled back: booking remains confirmed and capacity remains 4
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertSame('paid', $booking->fresh()->payment_status);
        $this->assertSame(4, $tier->fresh()->booked_count);
    }
}
