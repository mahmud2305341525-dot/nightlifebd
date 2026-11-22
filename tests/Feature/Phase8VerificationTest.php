<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventTicketTier;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Phase8VerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_mode_database_seeder_creates_no_venues_reports_or_events(): void
    {
        config(['nightlife.demo_enabled' => false]);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('venues', 0);
        $this->assertDatabaseCount('reports', 0);
        $this->assertDatabaseCount('events', 0);
    }

    public function test_provider_down_routes_remain_honest_and_fail_closed_in_production(): void
    {
        $this->withoutMiddleware();
        app()->detectEnvironment(fn () => 'production');
        config(['services.ors.key' => null, 'nightlife.routing_driver' => 'ors']);
        Http::fake(['*' => Http::response('down', 503)]);

        $this->postJson('/route/compute', [
            'from_lat' => 23.7806,
            'from_lng' => 90.4075,
            'to_lat' => 23.7900,
            'to_lng' => 90.4100,
        ])
            ->assertStatus(503)
            ->assertJsonStructure(['error']);
    }

    public function test_paid_booking_is_not_confirmed_when_payment_gateway_is_none(): void
    {
        config(['nightlife.demo_enabled' => false, 'nightlife.payments_driver' => 'none']);
        $user = User::factory()->create();
        $venue = Venue::create([
            'name' => 'Paid Event Venue',
            'category' => 'event',
            'area' => 'Gulshan',
            'price_tier' => 2,
            'lat' => 23.790,
            'lng' => 90.410,
            'status' => 'approved',
        ]);
        $event = Event::create([
            'venue_id' => $venue->id,
            'organizer_id' => $user->id,
            'title' => 'Paid Event',
            'slug' => 'paid-event',
            'category' => 'concert',
            'area' => 'Gulshan',
            'address' => 'Gulshan',
            'lat' => 23.790,
            'lng' => 90.410,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(3),
            'description' => 'A paid event.',
            'status' => 'published',
        ]);
        $tier = EventTicketTier::create([
            'event_id' => $event->id,
            'name' => 'General',
            'price' => 500,
            'capacity' => 10,
            'booked_count' => 0,
            'max_per_user' => 5,
        ]);

        $this->actingAs($user)->post(route('events.book', $event), [
            'ticket_tier_id' => $tier->id,
            'quantity' => 1,
            'payment_method' => 'mock_bkash',
        ])
            ->assertSessionHasErrors('payment_method');

        $this->assertDatabaseCount('event_bookings', 0);
    }
}
