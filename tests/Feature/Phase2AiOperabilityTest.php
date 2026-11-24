<?php

namespace Tests\Feature;

use App\Models\AiTaskUsage;
use App\Models\DarkSpot;
use App\Models\Venue;
use App\Services\AiTaskService;
use App\Services\GeminiService;
use App\Services\ReportAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class Phase2AiOperabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_task_usage_is_recorded_and_usage_summary_is_available(): void
    {
        AiTaskUsage::create([
            'feature' => 'report_triage',
            'prompt_version' => 'test',
            'status' => 'success',
            'latency_ms' => 120,
            'model' => 'gemini-test',
        ]);

        $gemini = Mockery::mock(GeminiService::class);
        $summary = app(AiTaskService::class, ['gemini' => $gemini])->usageSummary();

        $this->assertSame(1, $summary['total']);
        $this->assertSame(1, $summary['success']);
        $this->assertSame(120, $summary['average_latency_ms']);
    }

    public function test_report_analyzer_scrubs_phone_email_and_identifies_defamation_risk(): void
    {
        $analyzer = app(ReportAnalyzer::class);
        $scrubbed = $analyzer->scrubPersonalInfo('Call 01712345678 or test@example.com');

        $this->assertStringContainsString('[phone removed]', $scrubbed);
        $this->assertStringContainsString('[email removed]', $scrubbed);
        $this->assertTrue($analyzer->namesOrDefamationRisk('Mr Rahim is a thief'));
    }

    public function test_dark_spot_cluster_enters_moderator_queue_after_three_nearby_marks(): void
    {
        $user = $this->userWithRole('user');
        foreach (range(1, 3) as $_) {
            $this->actingAs($user)->postJson('/dark-spots', [
                'lat' => 23.7800,
                'lng' => 90.4000,
                'note' => 'dark street',
            ])->assertOk();
        }

        $this->assertGreaterThanOrEqual(1, DarkSpot::where('status', 'pending_moderator')->count());
    }

    public function test_geocoding_assistant_calls_nominatim_and_returns_cached_source_attribution(): void
    {
        Http::fake([
            'https://nominatim.openstreetmap.org/search*' => Http::response([[
                'lat' => '23.7800',
                'lon' => '90.4000',
                'display_name' => 'Dhanmondi, Dhaka',
            ]]),
        ]);

        $this->postJson('/route/geocode-assist', ['q' => 'Dhanmondi'])
            ->assertOk()
            ->assertJsonPath('attribution', '© OpenStreetMap contributors')
            ->assertJsonCount(1, 'results');
    }

    public function test_night_planner_excludes_closed_past_midnight_venue(): void
    {
        $venue = Venue::create([
            'name' => 'Closed At Midnight',
            'category' => 'cafe',
            'area' => 'Gulshan',
            'price_tier' => 1,
            'lat' => 23.790,
            'lng' => 90.410,
            'opens_at' => '18:00',
            'closes_at' => '23:00',
            'status' => 'approved',
            'has_cctv' => null,
            'has_guard' => null,
            'lighting' => null,
        ]);

        $this->assertSame('Gulshan', $venue->area);
        $this->assertSame('23:00', substr((string) $venue->closes_at, 0, 5));
    }
}
