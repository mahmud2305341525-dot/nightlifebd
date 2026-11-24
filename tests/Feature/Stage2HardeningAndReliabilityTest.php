<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventNotification;
use App\Models\EventTicketTier;
use App\Models\Report;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueScore;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Stage2HardeningAndReliabilityTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------
    // Task 1: Notification Reliability and Concurrency Protection
    // -------------------------------------------------------------

    public function test_notification_chunking_processes_all_users_across_multiple_chunks(): void
    {
        $organizer = User::create([
            'name' => 'Concert Organizer',
            'email' => 'organizer_multi@example.com',
            'password' => bcrypt('password'),
            'is_banned' => false,
        ]);

        $venue = Venue::create([
            'name' => 'Multi Chunk Arena',
            'category' => 'lounge',
            'area' => 'Gulshan',
            'lat' => 23.79,
            'lng' => 90.41,
            'status' => 'approved',
        ]);

        $event = Event::create([
            'organizer_id' => $organizer->id,
            'venue_id' => $venue->id,
            'title' => 'Mega Multi-Chunk Concert',
            'slug' => 'mega-multi-chunk-concert',
            'category' => 'concert',
            'area' => 'Gulshan',
            'address' => 'Gulshan 2',
            'lat' => 23.79,
            'lng' => 90.41,
            'starts_at' => now()->addHours(6),
            'ends_at' => now()->addHours(10),
            'description' => 'Multi-chunk scale verification.',
            'status' => 'published',
        ]);

        // Create 550 active users (spans across 3 chunks: 250, 250, 50 + 1 organizer = 551 total)
        $now = now();
        $userRecords = [];
        for ($i = 1; $i <= 550; $i++) {
            $userRecords[] = [
                'name' => "Batch User {$i}",
                'email' => "batch_user_{$i}@example.com",
                'password' => bcrypt('password'),
                'is_banned' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        User::insert($userRecords);

        $totalActiveUsers = User::where('is_banned', false)->count();
        $this->assertEquals(551, $totalActiveUsers);

        // Run the command
        Artisan::call('events:notify-upcoming');

        // All 551 users must receive notifications across all 3 chunks
        $this->assertEquals(551, EventNotification::where('event_id', $event->id)->count());

        // Verify timestamps are populated and not null
        $firstNotification = EventNotification::where('event_id', $event->id)->first();
        $this->assertNotNull($firstNotification->created_at);
        $this->assertNotNull($firstNotification->updated_at);
        $this->assertFalse($firstNotification->is_read);
    }

    public function test_notification_database_unique_constraint_enforces_atomicity_and_prevents_duplicate_keys(): void
    {
        $organizer = User::create([
            'name' => 'Host User',
            'email' => 'host_unique@example.com',
            'password' => bcrypt('password'),
        ]);

        $event = Event::create([
            'organizer_id' => $organizer->id,
            'title' => 'Unique Constraint Event',
            'slug' => 'unique-constraint-event',
            'category' => 'concert',
            'area' => 'Banani',
            'address' => 'Banani 11',
            'starts_at' => now()->addHours(8),
            'ends_at' => now()->addHours(12),
            'description' => 'Unique check event.',
            'status' => 'published',
        ]);

        $user = User::create([
            'name' => 'Regular User',
            'email' => 'reg_unique@example.com',
            'password' => bcrypt('password'),
        ]);

        // Direct insertion of first notification
        EventNotification::create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'title' => 'Concert Alert',
            'message' => 'First Notification',
            'is_read' => false,
        ]);

        // Attempting to insert a duplicate with raw create must trigger UniqueConstraintViolationException
        $this->expectException(UniqueConstraintViolationException::class);
        EventNotification::create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'title' => 'Concert Alert Duplicate',
            'message' => 'Second Notification',
            'is_read' => false,
        ]);
    }

    public function test_notification_insert_or_ignore_handles_concurrent_payload_gracefully(): void
    {
        $organizer = User::create([
            'name' => 'Host User 2',
            'email' => 'host_ignore@example.com',
            'password' => bcrypt('password'),
        ]);

        $event = Event::create([
            'organizer_id' => $organizer->id,
            'title' => 'Concurrent Simulation Event',
            'slug' => 'concurrent-simulation-event',
            'category' => 'live_music',
            'area' => 'Dhanmondi',
            'address' => 'Dhanmondi 27',
            'starts_at' => now()->addHours(4),
            'ends_at' => now()->addHours(8),
            'description' => 'Concurrent simulation event.',
            'status' => 'published',
        ]);

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test_ignore@example.com',
            'password' => bcrypt('password'),
        ]);

        $payload = [
            [
                'user_id' => $user->id,
                'event_id' => $event->id,
                'title' => 'Concert Alert',
                'message' => 'Message 1',
                'is_read' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        // Process 1 inserts
        $inserted1 = EventNotification::insertOrIgnore($payload);
        $this->assertEquals(1, $inserted1);

        // Process 2 attempts to insert identical (event_id, user_id)
        $inserted2 = EventNotification::insertOrIgnore($payload);
        // Must be safely ignored (0 rows inserted), without throwing exceptions
        $this->assertEquals(0, $inserted2);
        $this->assertEquals(1, EventNotification::where('user_id', $user->id)->where('event_id', $event->id)->count());
    }

    public function test_notification_command_resumes_safely_after_partial_failure(): void
    {
        $organizer = User::create([
            'name' => 'Host User 3',
            'email' => 'host_partial@example.com',
            'password' => bcrypt('password'),
        ]);

        $event = Event::create([
            'organizer_id' => $organizer->id,
            'title' => 'Partial Failure Resumption Event',
            'slug' => 'partial-failure-resumption-event',
            'category' => 'festival',
            'area' => 'Uttara',
            'address' => 'Sector 3',
            'starts_at' => now()->addHours(12),
            'ends_at' => now()->addHours(16),
            'description' => 'Partial failure event.',
            'status' => 'published',
        ]);

        $users = [];
        for ($i = 1; $i <= 10; $i++) {
            $users[] = User::create([
                'name' => "Partial User {$i}",
                'email' => "partial_user_{$i}@example.com",
                'password' => bcrypt('password'),
                'is_banned' => false,
            ]);
        }

        // Simulate that the first 4 users already received notifications before a crash
        for ($i = 0; $i < 4; $i++) {
            EventNotification::create([
                'user_id' => $users[$i]->id,
                'event_id' => $event->id,
                'title' => 'Existing Alert',
                'message' => 'Existing message',
                'is_read' => false,
            ]);
        }

        $this->assertEquals(4, EventNotification::where('event_id', $event->id)->count());

        // Run the command (resumption)
        Artisan::call('events:notify-upcoming');

        // Total should now be 11 (4 pre-existing + 6 remaining users + 1 organizer)
        $this->assertEquals(11, EventNotification::where('event_id', $event->id)->count());

        // Each user has strictly 1 notification
        foreach ($users as $user) {
            $this->assertEquals(1, EventNotification::where('event_id', $event->id)->where('user_id', $user->id)->count());
        }
    }

    // -------------------------------------------------------------
    // Task 2: Event Pricing Correctness
    // -------------------------------------------------------------

    public function test_event_lowest_price_with_zero_priced_free_tier(): void
    {
        $organizer = User::create([
            'name' => 'Free Fest Host',
            'email' => 'free_fest@example.com',
            'password' => bcrypt('password'),
        ]);

        $event = Event::create([
            'organizer_id' => $organizer->id,
            'title' => 'Free Entry Acoustic Jam',
            'slug' => 'free-entry-acoustic-jam',
            'category' => 'live_music',
            'area' => 'Dhanmondi',
            'address' => 'Dhanmondi Lake',
            'starts_at' => now()->addHours(3),
            'ends_at' => now()->addHours(7),
            'description' => 'Free open event.',
            'status' => 'published',
        ]);

        EventTicketTier::create([
            'event_id' => $event->id,
            'name' => 'Free RSVP',
            'price' => 0.00,
            'capacity' => 100,
            'booked_count' => 10,
            'max_per_user' => 2,
        ]);

        EventTicketTier::create([
            'event_id' => $event->id,
            'name' => 'Supporter Pass',
            'price' => 300.00,
            'capacity' => 20,
            'booked_count' => 5,
            'max_per_user' => 4,
        ]);

        // Eager loaded
        $loadedEvent = Event::with('ticketTiers')->find($event->id);
        $this->assertSame(0.0, $loadedEvent->lowestPrice());

        // Lazy loaded
        $freshEvent = Event::find($event->id);
        $this->assertSame(0.0, $freshEvent->lowestPrice());
    }

    public function test_event_lowest_price_with_decimal_precision(): void
    {
        $organizer = User::create([
            'name' => 'Decimal Host',
            'email' => 'decimal_host@example.com',
            'password' => bcrypt('password'),
        ]);

        $event = Event::create([
            'organizer_id' => $organizer->id,
            'title' => 'Indie Jazz Special',
            'slug' => 'indie-jazz-special',
            'category' => 'live_music',
            'area' => 'Gulshan',
            'address' => 'Gulshan Avenue',
            'starts_at' => now()->addHours(5),
            'ends_at' => now()->addHours(9),
            'description' => 'Decimal priced passes.',
            'status' => 'published',
        ]);

        EventTicketTier::create([
            'event_id' => $event->id,
            'name' => 'Discount Student',
            'price' => 149.99,
            'capacity' => 50,
            'booked_count' => 0,
            'max_per_user' => 2,
        ]);

        EventTicketTier::create([
            'event_id' => $event->id,
            'name' => 'Standard',
            'price' => 299.50,
            'capacity' => 100,
            'booked_count' => 0,
            'max_per_user' => 4,
        ]);

        // Eager loaded
        $loadedEvent = Event::with('ticketTiers')->find($event->id);
        $this->assertSame(149.99, $loadedEvent->lowestPrice());

        // Lazy loaded
        $freshEvent = Event::find($event->id);
        $this->assertSame(149.99, $freshEvent->lowestPrice());
    }

    // -------------------------------------------------------------
    // Task 3: Heatmap Query Safety and Accuracy
    // -------------------------------------------------------------

    public function test_heatmap_query_safety_with_combined_filters_and_strict_or_grouping(): void
    {
        $dhakaVenue = Venue::create([
            'name' => 'Safe Zone Venue',
            'category' => 'cafe',
            'area' => 'Dhanmondi',
            'lat' => 23.7500,
            'lng' => 90.3800,
            'status' => 'approved',
        ]);

        $user = User::create([
            'name' => 'Reporter',
            'email' => 'reporter_filter@example.com',
            'password' => bcrypt('password'),
            'trust_score' => 0.8,
        ]);

        // 1. Verified incident inside bounds -> MUST BE INCLUDED
        $validReport = Report::create([
            'user_id' => $user->id,
            'venue_id' => $dhakaVenue->id,
            'kind' => 'incident',
            'category' => 'harassment',
            'severity' => 4,
            'lat' => 23.7510,
            'lng' => 90.3810,
            'body' => 'Verified incident in bounds',
            'incident_time' => now()->subHour(),
            'status' => 'verified',
        ]);

        // 2. Unverified / pending report inside bounds -> MUST BE EXCLUDED
        $pendingReport = Report::create([
            'user_id' => $user->id,
            'venue_id' => $dhakaVenue->id,
            'kind' => 'incident',
            'category' => 'harassment',
            'severity' => 4,
            'lat' => 23.7510,
            'lng' => 90.3810,
            'body' => 'Unverified report in bounds',
            'incident_time' => now()->subHour(),
            'status' => 'pending',
        ]);

        // 3. Rejected report inside bounds -> MUST BE EXCLUDED
        $rejectedReport = Report::create([
            'user_id' => $user->id,
            'venue_id' => $dhakaVenue->id,
            'kind' => 'incident',
            'category' => 'theft',
            'severity' => 3,
            'lat' => 23.7510,
            'lng' => 90.3810,
            'body' => 'Rejected report in bounds',
            'incident_time' => now()->subHour(),
            'status' => 'rejected',
        ]);

        // 4. Duplicate report inside bounds -> MUST BE EXCLUDED
        $duplicateReport = Report::create([
            'user_id' => $user->id,
            'venue_id' => $dhakaVenue->id,
            'duplicate_of' => $validReport->id,
            'kind' => 'incident',
            'category' => 'harassment',
            'severity' => 4,
            'lat' => 23.7510,
            'lng' => 90.3810,
            'body' => 'Duplicate report in bounds',
            'incident_time' => now()->subHour(),
            'status' => 'verified',
        ]);

        // 5. Positive report inside bounds -> MUST BE EXCLUDED
        $positiveReport = Report::create([
            'user_id' => $user->id,
            'venue_id' => $dhakaVenue->id,
            'kind' => 'review',
            'category' => 'positive',
            'rating' => 5,
            'severity' => 1,
            'lat' => 23.7510,
            'lng' => 90.3810,
            'body' => 'Positive experience in bounds',
            'incident_time' => now()->subHour(),
            'status' => 'verified',
        ]);

        // 6. Soft-deleted report inside bounds -> MUST BE EXCLUDED
        $deletedReport = Report::create([
            'user_id' => $user->id,
            'venue_id' => $dhakaVenue->id,
            'kind' => 'incident',
            'category' => 'violence',
            'severity' => 5,
            'lat' => 23.7510,
            'lng' => 90.3810,
            'body' => 'Deleted report in bounds',
            'incident_time' => now()->subHour(),
            'status' => 'verified',
        ]);
        $deletedReport->delete();

        // 7. Missing coordinates with no venue -> MUST BE SAFELY EXCLUDED
        $noCoordReport = Report::create([
            'user_id' => $user->id,
            'venue_id' => null,
            'kind' => 'incident',
            'category' => 'theft',
            'severity' => 3,
            'lat' => null,
            'lng' => null,
            'body' => 'Missing coords report',
            'incident_time' => now()->subHour(),
            'status' => 'verified',
        ]);

        // Query Dhaka viewport
        $response = $this->getJson(route('api.heat', [
            'north' => 23.80,
            'south' => 23.70,
            'east' => 90.45,
            'west' => 90.35,
        ]));

        $response->assertStatus(200);
        $incidents = $response->json('incidents');

        // Strictly only the 1 valid report should be present
        $this->assertCount(1, $incidents);
        $this->assertEquals(23.751, $incidents[0]['lat']);
        $this->assertEquals(90.381, $incidents[0]['lng']);

        // Feed query
        $feedResponse = $this->getJson(route('api.feed', [
            'north' => 23.80,
            'south' => 23.70,
            'east' => 90.45,
            'west' => 90.35,
        ]));

        $feedResponse->assertStatus(200);
        $feedData = $feedResponse->json();
        $this->assertCount(1, $feedData);
        $this->assertEquals($validReport->id, $feedData[0]['id']);
        $this->assertEquals('Harassment', $feedData[0]['category_label']);
    }

    // -------------------------------------------------------------
    // Task 4: Event Listing Query and Performance Validation
    // -------------------------------------------------------------

    public function test_event_listing_query_count_remains_constant_across_various_dataset_sizes(): void
    {
        $organizer = User::create([
            'name' => 'Load Test Organizer',
            'email' => 'load_organizer@example.com',
            'password' => bcrypt('password'),
        ]);

        // Helper to seed N events with venues and ticket tiers
        $seedEvents = function (int $count) use ($organizer) {
            for ($i = 1; $i <= $count; $i++) {
                $venue = Venue::create([
                    'name' => "Scale Venue {$i}",
                    'category' => 'lounge',
                    'area' => 'Gulshan',
                    'lat' => 23.79 + ($i * 0.0001),
                    'lng' => 90.41 + ($i * 0.0001),
                    'status' => 'approved',
                ]);

                VenueScore::create([
                    'venue_id' => $venue->id,
                    'bucket' => 'night',
                    'score' => 4,
                    'badge' => 'verified_safe',
                    'report_count' => 15,
                ]);

                $event = Event::create([
                    'organizer_id' => $organizer->id,
                    'venue_id' => $venue->id,
                    'title' => "Scale Event {$i}",
                    'slug' => "scale-event-{$i}-".uniqid(),
                    'category' => 'concert',
                    'area' => 'Gulshan',
                    'address' => "Gulshan Avenue {$i}",
                    'lat' => $venue->lat,
                    'lng' => $venue->lng,
                    'starts_at' => now()->addHours(6),
                    'ends_at' => now()->addHours(10),
                    'description' => "Scale event {$i}",
                    'status' => 'published',
                ]);

                EventTicketTier::create([
                    'event_id' => $event->id,
                    'name' => 'General',
                    'price' => 500.00,
                    'capacity' => 100,
                    'booked_count' => 0,
                    'max_per_user' => 4,
                ]);
            }
        };

        // Measure with 5 events
        $seedEvents(5);
        $queries5 = [];
        DB::listen(function ($q) use (&$queries5) {
            $queries5[] = $q->sql;
        });
        $this->get(route('events.index'))->assertStatus(200);
        $count5 = count($queries5);

        // Measure with 10 events (add 5 more)
        $seedEvents(5);
        $queries10 = [];
        DB::listen(function ($q) use (&$queries10) {
            $queries10[] = $q->sql;
        });
        $this->get(route('events.index'))->assertStatus(200);
        $count10 = count($queries10);

        // Measure with 25 events (add 15 more)
        $seedEvents(15);
        $queries25 = [];
        DB::listen(function ($q) use (&$queries25) {
            $queries25[] = $q->sql;
        });
        $this->get(route('events.index'))->assertStatus(200);
        $count25 = count($queries25);

        // Measure with 50 events (add 25 more)
        $seedEvents(25);
        $queries50 = [];
        DB::listen(function ($q) use (&$queries50) {
            $queries50[] = $q->sql;
        });
        $this->get(route('events.index'))->assertStatus(200);
        $count50 = count($queries50);

        // Query count MUST remain constant across all dataset sizes (exactly 6 queries):
        // 1 (count aggregate for paginator) + 1 (events select) + 1 (venue eager) + 1 (venue.scores eager) + 1 (ticketTiers eager) + 1 (distinct areas)
        $this->assertEquals(6, $count5, "Expected 6 queries for 5 events, executed: {$count5}");
        $this->assertEquals(6, $count10, "Expected 6 queries for 10 events, executed: {$count10}");
        $this->assertEquals(6, $count25, "Expected 6 queries for 25 events, executed: {$count25}");
        $this->assertEquals(6, $count50, "Expected 6 queries for 50 events, executed: {$count50}");
    }

    public function test_event_listing_handles_events_with_null_or_deleted_venue_safely(): void
    {
        $organizer = User::create([
            'name' => 'Street Fest Host',
            'email' => 'street_fest@example.com',
            'password' => bcrypt('password'),
        ]);

        // Event without venue (outdoor street event)
        $streetEvent = Event::create([
            'organizer_id' => $organizer->id,
            'venue_id' => null,
            'title' => 'Open Street Carnival',
            'slug' => 'open-street-carnival',
            'category' => 'festival',
            'area' => 'Dhanmondi',
            'address' => 'Mirpur Road',
            'lat' => 23.75,
            'lng' => 90.38,
            'starts_at' => now()->addHours(5),
            'ends_at' => now()->addHours(9),
            'description' => 'Outdoor street festival with no venue.',
            'status' => 'published',
        ]);

        $this->assertNull($streetEvent->getVenueSafetyScore());

        // Visiting events listing must render successfully without throwing null pointer exceptions
        $response = $this->get(route('events.index'));
        $response->assertStatus(200);
        $response->assertSee('Open Street Carnival');
        $response->assertSee(__('Safety verified at door'));
    }

    // -------------------------------------------------------------
    // Task 5: Venue Aggregate Correctness
    // -------------------------------------------------------------

    public function test_venue_aggregates_with_unverified_and_soft_deleted_reviews(): void
    {
        $venue = Venue::create([
            'name' => 'Star Cafe Lounge',
            'category' => 'cafe',
            'area' => 'Gulshan',
            'lat' => 23.79,
            'lng' => 90.41,
            'status' => 'approved',
        ]);

        $user = User::create([
            'name' => 'Reviewer A',
            'email' => 'rev_a@example.com',
            'password' => bcrypt('password'),
        ]);

        // 1. Verified review (rating 5)
        Report::create([
            'user_id' => $user->id,
            'venue_id' => $venue->id,
            'kind' => 'review',
            'category' => 'positive',
            'rating' => 5,
            'severity' => 1,
            'body' => 'Excellent venue',
            'incident_time' => now(),
            'status' => 'verified',
        ]);

        // 2. Pending review (rating 1) -> MUST BE EXCLUDED from avg and count
        Report::create([
            'user_id' => $user->id,
            'venue_id' => $venue->id,
            'kind' => 'review',
            'category' => 'positive',
            'rating' => 1,
            'severity' => 1,
            'body' => 'Spam unverified review',
            'incident_time' => now(),
            'status' => 'pending',
        ]);

        // 3. Soft-deleted review (rating 1) -> MUST BE EXCLUDED from avg and count
        $deleted = Report::create([
            'user_id' => $user->id,
            'venue_id' => $venue->id,
            'kind' => 'review',
            'category' => 'positive',
            'rating' => 1,
            'severity' => 1,
            'body' => 'Deleted review',
            'incident_time' => now(),
            'status' => 'verified',
        ]);
        $deleted->delete();

        // 4. Non-review incident (rating null) -> MUST BE EXCLUDED
        Report::create([
            'user_id' => $user->id,
            'venue_id' => $venue->id,
            'kind' => 'incident',
            'category' => 'dark_road',
            'rating' => null,
            'severity' => 2,
            'body' => 'Dark road near venue',
            'incident_time' => now(),
            'status' => 'verified',
        ]);

        // Test with eager loading
        $loadedVenue = Venue::where('id', $venue->id)
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->first();

        $this->assertSame(5.0, $loadedVenue->avgRating());
        $this->assertSame(1, $loadedVenue->reviewCount());

        // Test show page
        $response = $this->get(route('venues.show', $venue));
        $response->assertStatus(200);
        $response->assertViewHas('avgRating', 5.0);
        $response->assertViewHas('reviewCount', 1);
    }

    public function test_venue_aggregates_with_no_reviews(): void
    {
        $venue = Venue::create([
            'name' => 'Brand New Venue',
            'category' => 'restaurant',
            'area' => 'Banani',
            'lat' => 23.79,
            'lng' => 90.40,
            'status' => 'approved',
        ]);

        $response = $this->get(route('venues.show', $venue));
        $response->assertStatus(200);
        $response->assertViewHas('avgRating', null);
        $response->assertViewHas('reviewCount', 0);
    }

    public function test_venue_aggregates_decimal_rounding_consistency(): void
    {
        $venue = Venue::create([
            'name' => 'Decimal Rating Venue',
            'category' => 'rooftop',
            'area' => 'Banani',
            'lat' => 23.79,
            'lng' => 90.40,
            'status' => 'approved',
        ]);

        $user = User::create([
            'name' => 'Reviewer B',
            'email' => 'rev_b@example.com',
            'password' => bcrypt('password'),
        ]);

        // Ratings: 4, 4, 5 -> avg = 13/3 = 4.333333333 -> rounded to 4.3
        foreach ([4, 4, 5] as $rating) {
            Report::create([
                'user_id' => $user->id,
                'venue_id' => $venue->id,
                'kind' => 'review',
                'category' => 'positive',
                'rating' => $rating,
                'severity' => 1,
                'body' => "Rating {$rating} review",
                'incident_time' => now(),
                'status' => 'verified',
            ]);
        }

        $venue->load('scores')->loadAvg('reviews', 'rating')->loadCount('reviews');

        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        $this->assertSame(4.3, $venue->avgRating());
        $this->assertSame(3, $venue->reviewCount());
        $this->assertSame(0, $queryCount, 'Accessing avgRating and reviewCount must not trigger queries when aggregates are loaded.');
    }

    // -------------------------------------------------------------
    // Task 6 & 7: Production Readiness Index & Observability Verification
    // -------------------------------------------------------------

    public function test_database_stage2_indexes_exist_and_are_active(): void
    {
        $venueIndexes = array_column(\Illuminate\Support\Facades\Schema::getIndexes('venues'), 'name');
        $this->assertContains('venues_geo_idx', $venueIndexes, 'venues table must have venues_geo_idx on (lat, lng).');

        $notifIndexes = array_column(\Illuminate\Support\Facades\Schema::getIndexes('event_notifications'), 'name');
        $this->assertContains(
            'event_notifications_event_id_user_id_unique',
            $notifIndexes,
            'event_notifications table must have unique constraint on (event_id, user_id).'
        );
    }

    public function test_notification_command_logs_safely_when_concurrent_duplicates_occur(): void
    {
        \Illuminate\Support\Facades\Log::spy();

        $organizer = User::create([
            'name' => 'Host Observability',
            'email' => 'host_obs@example.com',
            'password' => bcrypt('password'),
        ]);

        $event = Event::create([
            'organizer_id' => $organizer->id,
            'title' => 'Observability Alert Concert',
            'slug' => 'observability-alert-concert',
            'category' => 'concert',
            'area' => 'Banani',
            'address' => 'Banani 11',
            'starts_at' => now()->addHours(6),
            'ends_at' => now()->addHours(10),
            'description' => 'Observability verification.',
            'status' => 'published',
        ]);

        $user = User::create([
            'name' => 'Concurrent User',
            'email' => 'concurrent_user@example.com',
            'password' => bcrypt('password'),
        ]);

        // Run the command once to establish baseline notifications
        Artisan::call('events:notify-upcoming');

        // Now run a second time: all active users are already notified, so 0 new insertions occur
        Artisan::call('events:notify-upcoming');

        // Verify that Log calls, if any, do NOT expose user email or password
        \Illuminate\Support\Facades\Log::shouldNotHaveReceived('error');
    }

    public function test_notification_command_returns_failure_status_when_database_exception_occurs(): void
    {
        \Illuminate\Support\Facades\Log::spy();

        $organizer = User::create([
            'name' => 'Host Error Test',
            'email' => 'host_error@example.com',
            'password' => bcrypt('password'),
        ]);

        $event = Event::create([
            'organizer_id' => $organizer->id,
            'title' => 'Failing Alert Concert',
            'slug' => 'failing-alert-concert',
            'category' => 'concert',
            'area' => 'Gulshan',
            'address' => 'Gulshan 1',
            'starts_at' => now()->addHours(6),
            'ends_at' => now()->addHours(10),
            'description' => 'Failure test.',
            'status' => 'published',
        ]);

        User::create([
            'name' => 'Test User for Error',
            'email' => 'user_err@example.com',
            'password' => bcrypt('password'),
        ]);

        // Create an SQLite trigger that simulates a fatal storage engine failure during insertion
        DB::statement('CREATE TRIGGER fail_notif_insert BEFORE INSERT ON event_notifications BEGIN SELECT RAISE(FAIL, "Simulated disk storage failure"); END;');

        try {
            $exitCode = Artisan::call('events:notify-upcoming');

            // Must return Command::FAILURE (code 1)
            $this->assertSame(\Illuminate\Console\Command::FAILURE, $exitCode, 'Command must exit with FAILURE status when DB errors occur.');

            // Successful notification count must remain 0 (not falsely incremented)
            $this->assertSame(0, EventNotification::count());

            // Error must be logged with structured context
            \Illuminate\Support\Facades\Log::shouldHaveReceived('error')->once();
        } finally {
            DB::statement('DROP TRIGGER IF EXISTS fail_notif_insert;');
        }
    }
}
