<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpatialRateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_heat_endpoint_is_rate_limited(): void
    {
        // First request succeeds
        $this->getJson('/api/heat')->assertOk();

        // Send 60 requests to fill the throttle allowance
        for ($i = 0; $i < 60; $i++) {
            $res = $this->getJson('/api/heat');
            if ($res->status() === 429) {
                break;
            }
        }

        // Subsequent request must be throttled with 429 Too Many Requests
        $this->getJson('/api/heat')->assertStatus(429);
    }

    public function test_api_nearest_endpoint_is_rate_limited(): void
    {
        $this->getJson('/api/nearest?lat=23.78&lng=90.40')->assertOk();

        for ($i = 0; $i < 60; $i++) {
            $res = $this->getJson('/api/nearest?lat=23.78&lng=90.40');
            if ($res->status() === 429) {
                break;
            }
        }

        $this->getJson('/api/nearest?lat=23.78&lng=90.40')->assertStatus(429);
    }

    public function test_api_feed_endpoint_is_rate_limited(): void
    {
        $this->getJson('/api/feed')->assertOk();

        for ($i = 0; $i < 60; $i++) {
            $res = $this->getJson('/api/feed');
            if ($res->status() === 429) {
                break;
            }
        }

        $this->getJson('/api/feed')->assertStatus(429);
    }
}
