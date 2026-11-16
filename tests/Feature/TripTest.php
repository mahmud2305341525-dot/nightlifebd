<?php

namespace Tests\Feature;

use App\Models\Trip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripTest extends TestCase
{
    use RefreshDatabase;

    public function test_trip_link_works_then_expires_with_410(): void
    {
        $user = $this->userWithRole('user');
        $this->actingAs($user)->post('/trips', ['kind' => 'ride', 'duration' => 30, 'vehicle_plate' => 'ga 12-3456', 'destination' => 'Home'])->assertRedirect();

        $trip = Trip::first();
        $this->assertSame(40, strlen($trip->token));
        $this->assertSame('GA 12-3456', $trip->vehicle_plate);

        $this->get('/track/'.$trip->token)->assertOk();
        $this->getJson('/track/'.$trip->token.'/data')->assertOk()->assertJson(['active' => true]);

        $trip->update(['expires_at' => now()->subMinute()]);
        $this->get('/track/'.$trip->token)->assertStatus(410);
        $this->getJson('/track/'.$trip->token.'/data')->assertStatus(410);
    }

    public function test_only_owner_can_ping_and_pings_are_stored(): void
    {
        $owner = $this->userWithRole('user');
        $other = $this->userWithRole('user');
        $trip = $owner->trips()->create(['token' => str_repeat('a', 40), 'expires_at' => now()->addHour()]);

        $this->actingAs($other)->postJson('/trips/'.$trip->token.'/ping', ['lat' => 23.7, 'lng' => 90.4])->assertForbidden();
        $this->actingAs($owner)->postJson('/trips/'.$trip->token.'/ping', ['lat' => 23.7, 'lng' => 90.4])->assertOk();

        $this->assertDatabaseCount('trip_pings', 1);
        $this->assertEqualsWithDelta(23.7, $trip->fresh()->last_lat, 0.0001);
    }

    public function test_expired_trip_rejects_pings(): void
    {
        $owner = $this->userWithRole('user');
        $trip = $owner->trips()->create(['token' => str_repeat('b', 40), 'expires_at' => now()->subMinute()]);
        $this->actingAs($owner)->postJson('/trips/'.$trip->token.'/ping', ['lat' => 23.7, 'lng' => 90.4])->assertStatus(410);
    }

    public function test_user_cannot_start_multiple_simultaneous_active_trips(): void
    {
        $user = $this->userWithRole('user');
        $user->emergencyContacts()->create(['name' => 'Dad', 'phone' => '01712345678']);

        // First trip starts successfully
        $this->actingAs($user)->post('/trips', [
            'kind' => 'ride',
            'duration' => 30,
            'destination' => 'Campus',
            'vehicle_plate' => 'DHAKA-METRO-GA 11-2233',
        ])->assertRedirect();

        $this->assertDatabaseCount('trips', 1);

        // Attempting to start a second trip while first is still active is rejected
        $this->actingAs($user)->post('/trips', [
            'kind' => 'ride',
            'duration' => 30,
            'destination' => 'Home',
        ])->assertSessionHasErrors('destination');

        $this->assertDatabaseCount('trips', 1);

        // Once the first trip ends, starting a new trip succeeds
        $firstTrip = $user->trips()->first();
        $this->actingAs($user)->post("/trips/{$firstTrip->token}/end")->assertRedirect();
        $this->assertNotNull($firstTrip->fresh()->ended_at);

        $this->actingAs($user)->post('/trips', [
            'kind' => 'trip',
            'duration' => 45,
            'destination' => 'Home',
        ])->assertRedirect();

        $this->assertDatabaseCount('trips', 2);
    }

    public function test_purge_trips_command_deletes_pings_older_than_72_hours(): void
    {
        $user = $this->userWithRole('user');

        $this->travelTo(now());

        try {
            // Case 1: Trip ended 80 hours ago (> 72h) - should be purged
            $tripOldEnded = $user->trips()->create([
                'token' => str_repeat('c', 40),
                'expires_at' => now()->subHours(81),
                'ended_at' => now()->subHours(80),
                'last_lat' => 23.75,
                'last_lng' => 90.38,
            ]);
            $tripOldEnded->pings()->create(['lat' => 23.75, 'lng' => 90.38, 'created_at' => now()->subHours(80)]);
            $tripOldEnded->pings()->create(['lat' => 23.76, 'lng' => 90.39, 'created_at' => now()->subHours(80)]);

            // Case 2: Trip expired without explicit end 75 hours ago (> 72h) - should be purged
            $tripOldExpired = $user->trips()->create([
                'token' => str_repeat('d', 40),
                'expires_at' => now()->subHours(75),
                'ended_at' => null,
                'last_lat' => 23.77,
                'last_lng' => 90.40,
            ]);
            $tripOldExpired->pings()->create(['lat' => 23.77, 'lng' => 90.40, 'created_at' => now()->subHours(75)]);

            // Case 3: Trip ended 24 hours ago (< 72h) - should be retained
            $tripRecentEnded = $user->trips()->create([
                'token' => str_repeat('e', 40),
                'expires_at' => now()->subHours(25),
                'ended_at' => now()->subHours(24),
                'last_lat' => 23.78,
                'last_lng' => 90.41,
            ]);
            $tripRecentEnded->pings()->create(['lat' => 23.78, 'lng' => 90.41, 'created_at' => now()->subHours(24)]);

            // Case 4: Active trip - should be retained
            $tripActive = $user->trips()->create([
                'token' => str_repeat('f', 40),
                'expires_at' => now()->addMinutes(30),
                'ended_at' => null,
                'last_lat' => 23.79,
                'last_lng' => 90.42,
            ]);
            $tripActive->pings()->create(['lat' => 23.79, 'lng' => 90.42, 'created_at' => now()]);

            // Initial ping count: 2 + 1 + 1 + 1 = 5
            $this->assertDatabaseCount('trip_pings', 5);

            // Execute the purge command
            $this->artisan('trips:purge')
                ->expectsOutput('Purged 3 location pings from 2 old trips.')
                ->assertSuccessful();

            // Pings for trips older than 72h were deleted; recent and active pings remain
            $this->assertDatabaseCount('trip_pings', 2);
            $this->assertDatabaseMissing('trip_pings', ['trip_id' => $tripOldEnded->id]);
            $this->assertDatabaseMissing('trip_pings', ['trip_id' => $tripOldExpired->id]);
            $this->assertDatabaseHas('trip_pings', ['trip_id' => $tripRecentEnded->id]);
            $this->assertDatabaseHas('trip_pings', ['trip_id' => $tripActive->id]);

            // Trip metadata records remain intact (not deleted), but coordinates are nullified for privacy
            $this->assertDatabaseCount('trips', 4);
            $this->assertNull($tripOldEnded->fresh()->last_lat);
            $this->assertNull($tripOldEnded->fresh()->last_lng);
            $this->assertNull($tripOldExpired->fresh()->last_lat);
            $this->assertNull($tripOldExpired->fresh()->last_lng);
            $this->assertNotNull($tripRecentEnded->fresh()->last_lat);
            $this->assertNotNull($tripActive->fresh()->last_lat);

            // Running again when no eligible pings remain behaves safely
            $this->artisan('trips:purge')
                ->expectsOutput('Purged 0 location pings from 2 old trips.')
                ->assertSuccessful();
        } finally {
            $this->travelBack();
        }
    }

    public function test_manually_ended_trip_returns_410_to_public_tracking_and_data_endpoints(): void
    {
        $user = $this->userWithRole('user');
        $trip = $user->trips()->create([
            'token' => str_repeat('x', 40),
            'expires_at' => now()->addMinutes(45),
            'last_lat' => 23.79,
            'last_lng' => 90.41,
        ]);

        // While active, track view returns 200 and data returns active: true
        $this->get('/track/'.$trip->token)->assertOk();
        $this->getJson('/track/'.$trip->token.'/data')->assertOk()->assertJson([
            'active' => true,
            'lat' => 23.79,
            'lng' => 90.41,
        ]);

        // User manually ends the trip
        $this->actingAs($user)->post('/trips/'.$trip->token.'/end')->assertRedirect();
        $this->assertNotNull($trip->fresh()->ended_at);

        // Public track view now returns HTTP 410 with the expired view
        $resView = $this->get('/track/'.$trip->token);
        $resView->assertStatus(410);
        $resView->assertViewIs('trips.expired');

        // Polling JSON endpoint returns HTTP 410 with active: false
        $resJson = $this->getJson('/track/'.$trip->token.'/data');
        $resJson->assertStatus(410);
        $resJson->assertJson([
            'active' => false,
            'message' => 'This trip has ended or the sharing link has expired.',
        ]);

        // Response does not expose private email or phone numbers
        $resJson->assertJsonMissing(['email' => $user->email]);
        $resJson->assertJsonMissing(['phone' => $user->phone]);

        // Owner pings are rejected with HTTP 410
        $this->actingAs($user)->postJson('/trips/'.$trip->token.'/ping', [
            'lat' => 23.80,
            'lng' => 90.42,
        ])->assertStatus(410);
    }
}
