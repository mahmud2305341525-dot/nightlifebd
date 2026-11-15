<?php

namespace Tests;

use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite(); // no `npm run build` needed to run the tests
        // keep tests offline and deterministic even if your .env contains real keys
        config(['services.gemini.key' => null, 'services.ors.key' => null, 'services.telegram.token' => null]);
    }

    protected function userWithRole(string $role = 'user', array $attrs = []): User
    {
        Role::findOrCreate($role);
        $u = User::factory()->create($attrs);
        $u->assignRole($role);

        return $u;
    }

    protected function makeVenue(array $o = []): Venue
    {
        return Venue::create($o + [
            'name' => 'Test Venue', 'category' => 'cafe', 'area' => 'Gulshan', 'price_tier' => 2,
            'lat' => 23.79, 'lng' => 90.40, 'has_cctv' => true, 'has_guard' => true, 'lighting' => 4,
            'status' => 'approved', 'opens_at' => '17:00', 'closes_at' => '01:00',
        ]);
    }
}
