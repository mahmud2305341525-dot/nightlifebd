<?php

namespace Tests\Feature;

use App\Models\EvidenceCandidate;
use App\Models\SafeHaven;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Phase1RealDataPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_osm_venue_dry_run_reports_unverified_candidate_without_saving(): void
    {
        Http::fake([
            'https://overpass-api.de/api/interpreter' => Http::response([
                'elements' => [[
                    'type' => 'node',
                    'id' => 12345,
                    'lat' => 23.790,
                    'lon' => 90.410,
                    'tags' => ['name:en' => 'Real Dhaka Cafe', 'amenity' => 'cafe'],
                ]],
            ]),
        ]);

        $this->artisan('venues:import', ['--area' => 'Dhaka Metropolitan', '--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame(0, Venue::count());
    }

    public function test_osm_venue_import_leaves_security_features_unknown(): void
    {
        Http::fake([
            'https://overpass-api.de/api/interpreter' => Http::response([
                'elements' => [[
                    'type' => 'way',
                    'id' => 98765,
                    'center' => ['lat' => 23.790, 'lon' => 90.410],
                    'tags' => ['name:en' => 'Real Dhaka Restaurant', 'amenity' => 'restaurant', 'addr:district' => 'Gulshan'],
                ]],
            ]),
        ]);

        $this->artisan('venues:import', ['--area' => 'Dhaka Metropolitan'])
            ->assertSuccessful();

        $venue = Venue::where('source', 'osm')->firstOrFail();
        $this->assertSame('way/98765', $venue->source_id);
        $this->assertSame('unverified', $venue->verification_level);
        $this->assertNull($venue->has_cctv);
        $this->assertNull($venue->has_guard);
        $this->assertNull($venue->lighting);
        $this->assertStringContainsString('© OpenStreetMap contributors', $this->get('/venues/'.$venue->id)->getContent());
    }

    public function test_haven_import_marks_osm_rows_as_verified_source(): void
    {
        Http::fake([
            'https://overpass-api.de/api/interpreter' => Http::response([
                'elements' => [[
                    'type' => 'node',
                    'id' => 555,
                    'lat' => 23.800,
                    'lon' => 90.400,
                    'tags' => ['name:en' => 'Dhaka Police Box', 'amenity' => 'police'],
                ]],
            ]),
        ]);

        $this->artisan('havens:import', ['--dry-run' => true])
            ->assertSuccessful();
        $this->artisan('havens:import')
            ->assertSuccessful();

        $haven = SafeHaven::where('osm_id', 'node/555')->firstOrFail();
        $this->assertSame('osm', $haven->source);
        $this->assertNotNull($haven->last_verified_at);
    }

    public function test_claim_is_stored_for_moderator_review(): void
    {
        $venue = Venue::create([
            'name' => 'Claimable Venue',
            'category' => 'cafe',
            'area' => 'Gulshan',
            'price_tier' => 2,
            'lat' => 23.790,
            'lng' => 90.410,
            'status' => 'approved',
            'source' => 'osm',
            'source_id' => 'node/123',
            'verification_level' => 'unverified',
            'has_cctv' => null,
            'has_guard' => null,
            'lighting' => null,
        ]);
        $user = $this->userWithRole('venue_partner');

        $this->actingAs($user)
            ->post('/venues/'.$venue->id.'/claim', [
                'phone' => '01700000000',
                'email' => 'owner@example.com',
                'note' => 'I operate this venue.',
                'evidence' => UploadedFile::fake()->create('evidence.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHas('status');

        $this->assertDatabaseHas('venue_claims', [
            'venue_id' => $venue->id,
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
    }

    public function test_evidence_candidate_stays_unverified_and_private_from_safety_data(): void
    {
        $candidate = EvidenceCandidate::create([
            'source_url' => 'https://example.gov/notice',
            'category' => 'violence',
            'approximate_place' => 'Gulshan',
            'facts' => 'A public notice reports an incident.',
            'status' => 'unverified',
        ]);

        $this->assertSame('unverified', $candidate->status);
        $this->assertDatabaseCount('reports', 0);
    }
}
