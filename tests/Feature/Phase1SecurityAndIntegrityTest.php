<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase1SecurityAndIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_model_check_constraints_enforce_valid_ranges(): void
    {
        $user = User::factory()->create();

        // Valid severity (1-5) saves successfully
        $report = Report::create([
            'user_id' => $user->id,
            'body' => 'Valid report severity test',
            'incident_time' => now(),
            'severity' => 4,
            'rating' => 5,
        ]);
        $this->assertEquals(4, $report->severity);
        $this->assertEquals(5, $report->rating);

        // Invalid severity (> 5) fails validation
        $this->expectException(\InvalidArgumentException::class);
        Report::create([
            'user_id' => $user->id,
            'body' => 'Invalid severity test',
            'incident_time' => now(),
            'severity' => 6,
        ]);
    }

    public function test_report_rating_check_constraint_rejects_out_of_bounds(): void
    {
        $user = User::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        Report::create([
            'user_id' => $user->id,
            'body' => 'Invalid rating test',
            'incident_time' => now(),
            'severity' => 3,
            'rating' => 0,
        ]);
    }

    public function test_venue_check_constraints_enforce_lighting_and_price_tier(): void
    {
        // Valid lighting (1-5) and price_tier (1-3)
        $venue = Venue::create([
            'name' => 'Valid Safe Haven Cafe',
            'category' => 'cafe',
            'area' => 'Dhanmondi',
            'lat' => 23.75,
            'lng' => 90.38,
            'lighting' => 4,
            'price_tier' => 2,
        ]);
        $this->assertEquals(4, $venue->lighting);
        $this->assertEquals(2, $venue->price_tier);

        // Invalid lighting (> 5)
        try {
            Venue::create([
                'name' => 'Invalid Lighting Venue',
                'category' => 'cafe',
                'area' => 'Dhanmondi',
                'lat' => 23.75,
                'lng' => 90.38,
                'lighting' => 6,
                'price_tier' => 2,
            ]);
            $this->fail('Expected InvalidArgumentException for lighting > 5');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Lighting', $e->getMessage());
        }

        // Invalid price tier (> 3)
        try {
            Venue::create([
                'name' => 'Invalid Price Tier Venue',
                'category' => 'cafe',
                'area' => 'Dhanmondi',
                'lat' => 23.75,
                'lng' => 90.38,
                'lighting' => 3,
                'price_tier' => 4,
            ]);
            $this->fail('Expected InvalidArgumentException for price_tier > 3');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Price tier', $e->getMessage());
        }
    }

    public function test_duplicate_deletion_preserves_community_reports_and_promotes_earliest(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userC = User::factory()->create();

        // User A files root report
        $root = Report::create([
            'user_id' => $userA->id,
            'body' => 'Root incident report by User A',
            'incident_time' => now(),
            'severity' => 3,
            'status' => 'verified',
        ]);

        // User B files duplicate 1
        $dupe1 = Report::create([
            'user_id' => $userB->id,
            'body' => 'Duplicate corroboration by User B',
            'incident_time' => now(),
            'severity' => 3,
            'status' => 'verified',
            'duplicate_of' => $root->id,
        ]);

        // User C files duplicate 2
        $dupe2 = Report::create([
            'user_id' => $userC->id,
            'body' => 'Duplicate corroboration by User C',
            'incident_time' => now(),
            'severity' => 3,
            'status' => 'verified',
            'duplicate_of' => $root->id,
        ]);

        $this->assertEquals($root->id, $dupe1->duplicate_of);
        $this->assertEquals($root->id, $dupe2->duplicate_of);
        $this->assertEquals(2, $root->duplicates()->count());

        // Deleting root report: User B and C's reports must NOT be destroyed!
        $root->delete();

        // Root is soft-deleted
        $this->assertSoftDeleted('reports', ['id' => $root->id]);

        // Dupe 1 was promoted to root (duplicate_of is null)
        $dupe1->refresh();
        $dupe2->refresh();

        $this->assertNull($dupe1->duplicate_of, 'Earliest dupe should be promoted to primary incident');
        $this->assertEquals($dupe1->id, $dupe2->duplicate_of, 'Remaining dupe should point to newly promoted primary');
        $this->assertDatabaseHas('reports', ['id' => $dupe1->id]);
        $this->assertDatabaseHas('reports', ['id' => $dupe2->id]);
    }

    public function test_safety_badge_component_accessibility_and_contrast(): void
    {
        $view = $this->blade('<x-badge badge="safe" />');
        $view->assertSee('role="status"', false);
        $view->assertSee('aria-label="Safety rating: Safe"', false);
        $view->assertSee('🛡️');
        $view->assertSee('Safe');
        $view->assertSee('bg-emerald-600');

        $viewModerate = $this->blade('<x-badge badge="moderate" />');
        $viewModerate->assertSee('⚠️');
        $viewModerate->assertSee('Moderate');
        $viewModerate->assertSee('bg-amber-600');

        $viewCaution = $this->blade('<x-badge badge="caution" />');
        $viewCaution->assertSee('⛔');
        $viewCaution->assertSee('Caution');
        $viewCaution->assertSee('bg-rose-600');
    }

    public function test_env_gitignore_exclusion_and_example_placeholders(): void
    {
        $gitignore = file_get_contents(base_path('.gitignore'));
        $this->assertStringContainsString('.env', $gitignore);

        $envExample = file_get_contents(base_path('.env.example'));
        $this->assertStringContainsString('GEMINI_API_KEY=', $envExample);
        $this->assertStringNotContainsString('AIzaSy', $envExample, 'No actual Google API keys in .env.example');
        $this->assertStringContainsString('NIGHTLIFE_DEMO_ENABLED=false', $envExample);
    }
}
