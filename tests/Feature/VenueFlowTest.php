<?php

namespace Tests\Feature;

use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VenueFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_venue_stays_pending_until_admin_approves(): void
    {
        $partner = $this->userWithRole('venue_partner');
        $admin = $this->userWithRole('safety_admin');

        $this->actingAs($partner)->post('/partner/venues', [
            'name' => 'Pending Cafe', 'category' => 'cafe', 'area' => 'Banani', 'price_tier' => 2,
            'lat' => 23.79, 'lng' => 90.40, 'lighting' => 3,
        ])->assertRedirect();

        $venue = Venue::firstWhere('name', 'Pending Cafe');
        $this->assertSame('pending', $venue->status);
        $this->get('/venues')->assertDontSee('Pending Cafe');

        $this->actingAs($admin)->post('/admin/venues/'.$venue->id.'/approve')->assertRedirect();
        $this->assertSame('approved', $venue->fresh()->status);
        $this->assertNotNull($venue->fresh()->scoreFor('night'));
        $this->get('/venues')->assertSee('Pending Cafe');
    }

    public function test_restricted_venues_are_hidden_until_age_confirmed(): void
    {
        $this->makeVenue(['name' => 'Lounge X', 'category' => 'lounge', 'is_restricted' => true]);
        $user = $this->userWithRole('user', ['is_adult_confirmed' => false]);

        $this->actingAs($user)->get('/venues')->assertDontSee('Lounge X');
        $this->actingAs($user)->post('/age-confirm');
        $this->actingAs($user->fresh())->get('/venues')->assertSee('Lounge X');
    }

    public function test_venue_validation(): void
    {
        $partner = $this->userWithRole('venue_partner');
        $this->actingAs($partner)->post('/partner/venues', ['category' => 'cafe'])->assertSessionHasErrors(['name', 'area', 'lat']);
    }
}
