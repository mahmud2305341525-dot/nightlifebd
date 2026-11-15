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
}
