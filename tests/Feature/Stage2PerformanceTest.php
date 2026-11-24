<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventNotification;
use App\Models\EventTicketTier;
use App\Models\Report;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Stage2PerformanceTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------
    // Task 1: Fix Event Discovery N+1 Queries
    // -------------------------------------------------------------

    public function test_events_index_query_count_is_constant_and_does_not_n_plus_one(): void
    {
        $organizer = User::create([
            'name' => 'Event Organizer',
            'email' => 'organizer@example.com',
            'password' => bcrypt('password'),
        ]);

        // Seed 10 published events with venues, scores, and ticket tiers
        for ($i = 1; $i <= 10; $i++) {
            $venue = Venue::create([
                'name' => "Venue {$i}",
                'category' => 'lounge',
                'area' => 'Gulshan',
                'lat' => 23.7925 + ($i * 0.001),
                'lng' => 90.4078 + ($i * 0.001),
                'status' => 'approved',
            ]);

            VenueScore::create([
                'venue_id' => $venue->id,
                'bucket' => 'night',
                'score' => 4,
                'badge' => 'verified_safe',
                'report_count' => 10,
            ]);

            $event = Event::create([
                'organizer_id' => $organizer->id,
                'venue_id' => $venue->id,
                'title' => "Concert Night {$i}",
                'slug' => "concert-night-{$i}",
                'category' => 'concert',
                'area' => 'Gulshan',
                'address' => "Road {$i}, Gulshan",
                'lat' => $venue->lat,
                'lng' => $venue->lng,
                'starts_at' => now()->addHours(6),
                'ends_at' => now()->addHours(10),
                'description' => "Live performance number {$i}",
                'status' => 'published',
            ]);

            EventTicketTier::create([
                'event_id' => $event->id,
                'name' => 'General Admission',
                'price' => 500.00 + ($i * 50),
                'capacity' => 100,
                'booked_count' => 10,
                'max_per_user' => 4,
            ]);

            EventTicketTier::create([
                'event_id' => $event->id,
                'name' => 'VIP',
                'price' => 1500.00 + ($i * 100),
                'capacity' => 20,
                'booked_count' => 5,
                'max_per_user' => 2,
            ]);
        }

        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });

        $response = $this->get(route('events.index'));
        $response->assertStatus(200);

        // Without eager loading and in-memory lowestPrice:
        // 1 query (events) + 1 query (distinct areas) + 10 queries (lowestPrice) + 10 queries (venue.scores) = 22+ queries
        // With eager loading (venue.scores, ticketTiers) and in-memory lowestPrice:
        // 1 (events paginate) + 1 (count total) + 1 (ticketTiers eager) + 1 (venue eager) + 1 (venue.scores eager) + 1 (distinct areas) = 6 queries
        $this->assertLessThanOrEqual(6, count($queries), 'Expected at most 6 database queries on events.index, but executed: '.count($queries));
    }

    public function test_event_lowest_price_uses_in_memory_tiers_when_relation_loaded(): void
    {
        $organizer = User::create([
            'name' => 'Band Organizer',
            'email' => 'band@example.com',
            'password' => bcrypt('password'),
        ]);

        $venue = Venue::create([
            'name' => 'Test Venue',
            'category' => 'club',
            'area' => 'Banani',
            'lat' => 23.79,
            'lng' => 90.40,
            'status' => 'approved',
        ]);

        $event = Event::create([
            'organizer_id' => $organizer->id,
            'venue_id' => $venue->id,
            'title' => 'Indie Music Night',
            'slug' => 'indie-music-night',
            'category' => 'live_music',
            'area' => 'Banani',
            'address' => 'Road 11',
            'lat' => 23.79,
            'lng' => 90.40,
            'starts_at' => now()->addHours(4),
            'ends_at' => now()->addHours(8),
            'description' => 'A cozy indie musical gig.',
            'status' => 'published',
        ]);

        EventTicketTier::create([
            'event_id' => $event->id,
            'name' => 'Early Bird',
            'price' => 350.00,
            'capacity' => 30,
            'booked_count' => 0,
            'max_per_user' => 4,
        ]);

        EventTicketTier::create([
            'event_id' => $event->id,
            'name' => 'Regular',
            'price' => 700.00,
            'capacity' => 100,
            'booked_count' => 0,
            'max_per_user' => 4,
        ]);

        // Case 1: When relation is loaded, 0 queries should be executed during lowestPrice()
        $loadedEvent = Event::with('ticketTiers')->find($event->id);
        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        $this->assertEquals(350.00, $loadedEvent->lowestPrice());
        $this->assertSame(0, $queryCount, 'lowestPrice() must not trigger any queries when ticketTiers is already loaded.');

        // Case 2: When relation is not loaded, it falls back to DB aggregate query
        $freshEvent = Event::find($event->id);
        $this->assertEquals(350.00, $freshEvent->lowestPrice());

        // Case 3: When an event has no tiers, returns 0.0
        $emptyEvent = Event::create([
            'organizer_id' => $organizer->id,
            'title' => 'Free Open Mic',
            'slug' => 'free-open-mic',
            'category' => 'cultural',
            'area' => 'Dhanmondi',
            'address' => 'Dhanmondi 27',
            'lat' => 23.75,
            'lng' => 90.37,
            'starts_at' => now()->addHours(12),
            'ends_at' => now()->addHours(16),
            'description' => 'Open mic event with no tickets.',
            'status' => 'published',
        ]);
        $emptyLoaded = Event::with('ticketTiers')->find($emptyEvent->id);
        $this->assertSame(0.0, $emptyLoaded->lowestPrice());
    }

    // -------------------------------------------------------------
    // Task 2: Optimize Upcoming Event Notifications
    // -------------------------------------------------------------

    public function test_notify_upcoming_events_dispatches_with_chunking_and_prevents_duplicates(): void
    {
        $organizer = User::create([
            'name' => 'Concert Host',
            'email' => 'host@example.com',
            'password' => bcrypt('password'),
        ]);

        $venue = Venue::create([
            'name' => 'Dhaka Arena',
            'category' => 'lounge',
            'area' => 'Bashundhara',
            'lat' => 23.81,
            'lng' => 90.42,
            'status' => 'approved',
        ]);

        $event = Event::create([
            'organizer_id' => $organizer->id,
            'venue_id' => $venue->id,
            'title' => 'Mega Rock Fest',
            'slug' => 'mega-rock-fest',
            'category' => 'concert',
            'area' => 'Bashundhara',
            'address' => 'Bashundhara R/A',
            'lat' => 23.81,
            'lng' => 90.42,
            'starts_at' => now()->addHours(5), // Within 24h
            'ends_at' => now()->addHours(10),
            'description' => 'Rock concert with heavy sound.',
            'status' => 'published',
        ]);

        // Create 8 regular users and 2 banned users
        $activeUsers = [];
        for ($i = 1; $i <= 8; $i++) {
            $activeUsers[] = User::create([
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'password' => bcrypt('password'),
                'is_banned' => false,
            ]);
        }

        $bannedUser = User::create([
            'name' => 'Banned User',
            'email' => 'banned@example.com',
            'password' => bcrypt('password'),
            'is_banned' => true,
        ]);

        // Pre-notify User 1 to test deduplication
        EventNotification::create([
            'user_id' => $activeUsers[0]->id,
            'event_id' => $event->id,
            'title' => 'Concert Alert',
            'message' => 'Already notified',
            'is_read' => false,
        ]);

        $initialNotificationCount = EventNotification::count();
        $this->assertSame(1, $initialNotificationCount);

        // Run the command
        Artisan::call('events:notify-upcoming');

        // Total notifications should now be 9 (1 existing for user1 + 7 remaining active users + 1 organizer)
        $this->assertEquals(9, EventNotification::count());

        // Banned user must not have been notified
        $this->assertFalse(
            EventNotification::where('user_id', $bannedUser->id)->where('event_id', $event->id)->exists()
        );

        // User 1 must still have only 1 notification (not duplicated)
        $this->assertEquals(
            1,
            EventNotification::where('user_id', $activeUsers[0]->id)->where('event_id', $event->id)->count()
        );

        // Running the command again must be idempotent (0 new insertions)
        Artisan::call('events:notify-upcoming');
        $this->assertEquals(9, EventNotification::count());
    }

    // -------------------------------------------------------------
    // Task 3: Optimize Heatmap Queries
    // -------------------------------------------------------------

    public function test_heatmap_data_filters_viewport_properly_for_standalone_and_venue_reports(): void
    {
        $dhakaVenue = Venue::create([
            'name' => 'Dhaka Club Venue',
            'category' => 'club',
            'area' => 'Dhanmondi',
            'lat' => 23.7460,
            'lng' => 90.3750,
            'status' => 'approved',
        ]);

        $ctgVenue = Venue::create([
            'name' => 'Chittagong Port Venue',
            'category' => 'lounge',
            'area' => 'Agrabad',
            'lat' => 22.3250,
            'lng' => 91.8150,
            'status' => 'approved',
        ]);

        $user = User::create([
            'name' => 'Reporter User',
            'email' => 'reporter@example.com',
            'password' => bcrypt('password'),
            'trust_score' => 0.8,
        ]);

        // Report 1: Dhaka standalone report inside Dhaka bounds
        $dhakaStandalone = Report::create([
            'user_id' => $user->id,
            'venue_id' => null,
            'kind' => 'incident',
            'category' => 'dark_road',
            'severity' => 3,
            'lat' => 23.7500,
            'lng' => 90.3800,
            'body' => 'Dark road near Dhanmondi Lake',
            'incident_time' => now()->subHour(),
            'status' => 'verified',
        ]);

        // Report 2: Chittagong standalone report far outside Dhaka bounds
        $ctgStandalone = Report::create([
            'user_id' => $user->id,
            'venue_id' => null,
            'kind' => 'incident',
            'category' => 'harassment',
            'severity' => 4,
            'lat' => 22.3300,
            'lng' => 91.8200,
            'body' => 'Harassment incident in Chittagong',
            'incident_time' => now()->subHour(),
            'status' => 'verified',
        ]);

        // Report 3: Report linked to Dhaka venue (lat/lng null on report)
        $dhakaVenueReport = Report::create([
            'user_id' => $user->id,
            'venue_id' => $dhakaVenue->id,
            'kind' => 'incident',
            'category' => 'theft',
            'severity' => 2,
            'lat' => null,
            'lng' => null,
            'body' => 'Pickpocket near Dhaka venue gate',
            'incident_time' => now()->subHour(),
            'status' => 'verified',
        ]);

        // Report 4: Report linked to Chittagong venue (lat/lng null on report)
        $ctgVenueReport = Report::create([
            'user_id' => $user->id,
            'venue_id' => $ctgVenue->id,
            'kind' => 'incident',
            'category' => 'theft',
            'severity' => 3,
            'lat' => null,
            'lng' => null,
            'body' => 'Theft near Chittagong venue gate',
            'incident_time' => now()->subHour(),
            'status' => 'verified',
        ]);

        // Query Dhaka viewport
        $response = $this->getJson(route('api.heat', [
            'north' => 23.90,
            'south' => 23.60,
            'east' => 90.50,
            'west' => 90.20,
        ]));

        $response->assertStatus(200);
        $incidents = $response->json('incidents');

        // Should include Dhaka standalone (23.75, 90.38) and Dhaka venue (23.746, 90.375)
        $this->assertCount(2, $incidents, 'Expected exactly 2 Dhaka incidents in the viewport.');

        $lats = array_column($incidents, 'lat');
        $this->assertContains(23.75, $lats);
        $this->assertContains(23.746, $lats);
        $this->assertNotContains(22.33, $lats, 'Chittagong standalone report must be excluded.');
        $this->assertNotContains(22.325, $lats, 'Chittagong venue report must be excluded.');

        // Test feed endpoint viewport filtering
        $feedResponse = $this->getJson(route('api.feed', [
            'north' => 23.90,
            'south' => 23.60,
            'east' => 90.50,
            'west' => 90.20,
        ]));

        $feedResponse->assertStatus(200);
        $feedReports = $feedResponse->json();
        $feedIds = array_column($feedReports, 'id');

        $this->assertContains($dhakaStandalone->id, $feedIds);
        $this->assertContains($dhakaVenueReport->id, $feedIds);
        $this->assertNotContains($ctgStandalone->id, $feedIds);
        $this->assertNotContains($ctgVenueReport->id, $feedIds);
    }

    // -------------------------------------------------------------
    // Task 4 & 5: Venue Show Aggregates Eager Loading
    // -------------------------------------------------------------

    public function test_venue_show_loads_aggregates_properly(): void
    {
        $venue = Venue::create([
            'name' => 'Gulshan Bistro',
            'category' => 'restaurant',
            'area' => 'Gulshan',
            'lat' => 23.79,
            'lng' => 90.41,
            'status' => 'approved',
        ]);

        $user = User::create([
            'name' => 'Reviewer One',
            'email' => 'rev1@example.com',
            'password' => bcrypt('password'),
        ]);

        // Create 2 verified reviews with ratings
        Report::create([
            'user_id' => $user->id,
            'venue_id' => $venue->id,
            'kind' => 'review',
            'category' => 'positive',
            'rating' => 4,
            'severity' => 1,
            'body' => 'Great ambiance and safe parking',
            'incident_time' => now(),
            'status' => 'verified',
        ]);

        Report::create([
            'user_id' => $user->id,
            'venue_id' => $venue->id,
            'kind' => 'review',
            'category' => 'positive',
            'rating' => 5,
            'severity' => 1,
            'body' => 'Excellent security and polite staff',
            'incident_time' => now(),
            'status' => 'verified',
        ]);

        $response = $this->get(route('venues.show', $venue));
        $response->assertStatus(200);
        $response->assertViewHas('avgRating', 4.5);
        $response->assertViewHas('reviewCount', 2);
    }
}
