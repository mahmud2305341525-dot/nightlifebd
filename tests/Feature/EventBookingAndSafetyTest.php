<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventBooking;
use App\Models\EventNotification;
use App\Models\EventTicketTier;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueScore;
use App\Support\TimeBucket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventBookingAndSafetyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $admin;
    private Venue $venue;
    private Event $event;
    private EventTicketTier $tier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->admin = User::factory()->create(['email' => 'admin_test@nightlifebd.com']);
        $this->admin->assignRole('super_admin');

        $this->user = User::factory()->create([
            'email' => 'maruf@nightlifebd.com',
            'name' => 'Maruf Ahmed',
            'city' => 'Dhaka',
        ]);

        $this->venue = Venue::create([
            'owner_id' => $this->admin->id,
            'name' => 'Jamuna Convention Arena',
            'category' => 'event',
            'area' => 'Bashundhara',
            'lat' => 23.8150,
            'lng' => 90.4280,
            'status' => 'approved',
            'lighting' => 4,
            'has_cctv' => true,
            'has_guard' => true,
        ]);

        VenueScore::create([
            'venue_id' => $this->venue->id,
            'bucket' => TimeBucket::current(),
            'score' => 87,
            'confidence_level' => 'high',
            'badge' => 'safe',
        ]);

        $this->event = Event::create([
            'venue_id' => $this->venue->id,
            'organizer_id' => $this->admin->id,
            'title' => 'Dhaka Rock Fest 2026',
            'slug' => 'dhaka-rock-fest-2026',
            'category' => 'concert',
            'area' => 'Bashundhara',
            'address' => 'Bashundhara R/A, Dhaka',
            'lat' => 23.8150,
            'lng' => 90.4280,
            'starts_at' => now()->addHours(6),
            'ends_at' => now()->addHours(10),
            'description' => 'Rock concert with full security and safe route corridors.',
            'status' => 'published',
            'is_featured' => true,
        ]);

        $this->tier = EventTicketTier::create([
            'event_id' => $this->event->id,
            'name' => 'General Admission',
            'price' => 500.00,
            'capacity' => 10,
            'booked_count' => 0,
            'max_per_user' => 4,
        ]);
    }

    public function test_user_can_view_events_index_and_filter_by_category(): void
    {
        $res = $this->get(route('events.index'));
        $res->assertOk();
        $res->assertSee('Dhaka Rock Fest 2026');
        $res->assertSee('৳500');

        $filterRes = $this->get(route('events.index', ['category' => 'concert', 'area' => 'Bashundhara']));
        $filterRes->assertOk();
        $filterRes->assertSee('Dhaka Rock Fest 2026');

        $emptyRes = $this->get(route('events.index', ['category' => 'cultural']));
        $emptyRes->assertOk();
        $emptyRes->assertDontSee('Dhaka Rock Fest 2026');
    }

    public function test_user_can_view_event_detail_with_venue_safety_score(): void
    {
        $res = $this->actingAs($this->user)->get(route('events.show', $this->event->slug));
        $res->assertOk();
        $res->assertSee('Dhaka Rock Fest 2026');
        $res->assertSee('Jamuna Convention Arena');
        $res->assertSee('87'); // Rounded 86.5
        $res->assertSee('Plan Safe Route Here');
    }

    public function test_authenticated_user_can_book_tickets_and_view_digital_pass(): void
    {
        $res = $this->actingAs($this->user)->post(route('events.book', $this->event->id), [
            'ticket_tier_id' => $this->tier->id,
            'quantity' => 2,
            'payment_method' => 'mock_bkash',
        ]);

        $booking = EventBooking::where('user_id', $this->user->id)->first();
        $this->assertNotNull($booking);
        $this->assertEquals(2, $booking->quantity);
        $this->assertEquals(1000.00, $booking->total_price);
        $this->assertEquals('confirmed', $booking->status);
        $this->assertNotEmpty($booking->booking_reference);
        $this->assertNotEmpty($booking->qr_token);

        // Ticket tier booked count updated
        $this->assertEquals(2, $this->tier->fresh()->booked_count);

        $res->assertRedirect(route('events.bookings.show', $booking->booking_reference));

        // Follow to digital pass
        $passRes = $this->actingAs($this->user)->get(route('events.bookings.show', $booking->booking_reference));
        $passRes->assertOk();
        $passRes->assertSee($booking->booking_reference);
        $passRes->assertSee('Maruf Ahmed');
        $passRes->assertSee('Safe Route');
        $passRes->assertSee('Live Ride Share');
        $passRes->assertSee('Walk With Me');
        $passRes->assertSee('SOS Emergency');
    }

    public function test_booking_capacity_cannot_be_oversold(): void
    {
        // Set capacity to 2
        $this->tier->update(['capacity' => 2, 'booked_count' => 0]);

        // User books 2 tickets
        $this->actingAs($this->user)->post(route('events.book', $this->event->id), [
            'ticket_tier_id' => $this->tier->id,
            'quantity' => 2,
            'payment_method' => 'mock_bkash',
        ]);
        $this->assertEquals(2, $this->tier->fresh()->booked_count);

        // Another user attempts to book 1 ticket
        $otherUser = User::factory()->create();
        $res = $this->actingAs($otherUser)->post(route('events.book', $this->event->id), [
            'ticket_tier_id' => $this->tier->id,
            'quantity' => 1,
            'payment_method' => 'mock_bkash',
        ]);

        $res->assertSessionHasErrors('quantity');
        $this->assertEquals(2, $this->tier->fresh()->booked_count);
    }

    public function test_user_cannot_view_another_users_digital_pass(): void
    {
        $booking = EventBooking::create([
            'user_id' => $this->user->id,
            'event_id' => $this->event->id,
            'ticket_tier_id' => $this->tier->id,
            'quantity' => 1,
            'unit_price' => 500,
            'total_price' => 500,
            'payment_method' => 'mock_bkash',
            'payment_status' => 'paid',
            'status' => 'confirmed',
        ]);

        $stranger = User::factory()->create();

        $res = $this->actingAs($stranger)->get(route('events.bookings.show', $booking->booking_reference));
        $res->assertForbidden();
    }

    public function test_admin_can_scan_and_check_in_attendee_via_qr_token(): void
    {
        $booking = EventBooking::create([
            'user_id' => $this->user->id,
            'event_id' => $this->event->id,
            'ticket_tier_id' => $this->tier->id,
            'quantity' => 1,
            'unit_price' => 500,
            'total_price' => 500,
            'payment_method' => 'mock_bkash',
            'payment_status' => 'paid',
            'status' => 'confirmed',
        ]);

        // First scan: Success
        $scanRes = $this->actingAs($this->admin)->postJson(route('admin.events.checkin'), [
            'code' => $booking->qr_token,
        ]);

        $scanRes->assertOk();
        $scanRes->assertJsonFragment(['success' => true]);
        $this->assertEquals('checked_in', $booking->fresh()->status);
        $this->assertNotNull($booking->fresh()->checked_in_at);

        // Duplicate scan: Rejected (409 Conflict)
        $dupRes = $this->actingAs($this->admin)->postJson(route('admin.events.checkin'), [
            'code' => $booking->qr_token,
        ]);
        $dupRes->assertStatus(409);
        $dupRes->assertJsonFragment(['success' => false]);
    }

    public function test_user_can_cancel_unattended_booking(): void
    {
        $booking = EventBooking::create([
            'user_id' => $this->user->id,
            'event_id' => $this->event->id,
            'ticket_tier_id' => $this->tier->id,
            'quantity' => 2,
            'unit_price' => 500,
            'total_price' => 1000,
            'payment_method' => 'mock_bkash',
            'payment_status' => 'paid',
            'status' => 'confirmed',
        ]);
        $this->tier->increment('booked_count', 2);

        $res = $this->actingAs($this->user)->post(route('events.bookings.cancel', $booking->id));
        $res->assertRedirect();

        $this->assertEquals('cancelled', $booking->fresh()->status);
        $this->assertEquals(0, $this->tier->fresh()->booked_count);
    }

    public function test_notify_upcoming_events_command_creates_notifications(): void
    {
        // Event starts in 6 hours, which is within 24h
        $this->artisan('events:notify-upcoming')->assertSuccessful();

        $notif = EventNotification::where('user_id', $this->user->id)
            ->where('event_id', $this->event->id)
            ->first();

        $this->assertNotNull($notif);
        $this->assertStringContainsString('Dhaka Rock Fest 2026', $notif->title);

        $notifRes = $this->actingAs($this->user)->get(route('events.notifications'));
        $notifRes->assertOk();
        $notifRes->assertSee('Dhaka Rock Fest 2026');
    }
}
