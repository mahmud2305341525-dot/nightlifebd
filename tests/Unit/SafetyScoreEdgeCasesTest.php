<?php

namespace Tests\Unit;

use App\Models\Report;
use App\Models\User;
use App\Models\Venue;
use App\Services\SafetyScoreService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SafetyScoreEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    public function test_venue_with_no_reports_is_explicitly_labeled_as_checklist_only(): void
    {
        $venue = $this->makeVenue([
            'has_cctv' => true,
            'has_guard' => true,
            'lighting' => 3,
        ]);

        $service = app(SafetyScoreService::class);
        $result = $service->compute($venue, collect(), 'night');

        // Base: 40 + 20 + 20 + 12 = 92. Night adjust: -5 => 87
        $this->assertSame(87, $result['score']);
        $this->assertSame('safe', $result['badge']);
        $this->assertSame(0, $result['count']);
        $this->assertSame(0.0, $result['risk']);
        $this->assertStringContainsString('Based on the venue checklist only', $result['explanation']);
    }

    public function test_exponential_time_decay_diminishes_old_reports(): void
    {
        $venue = $this->makeVenue();
        $user = $this->userWithRole('user', ['trust_score' => 1.0]);

        // Fresh report (today)
        $freshReport = new Report([
            'incident_time' => now()->setTime(22, 0),
            'severity' => 5,
            'category' => 'violence',
            'status' => 'verified',
        ]);
        $freshReport->setRelation('user', $user);

        // Stale report (180 days ago)
        $staleReport = new Report([
            'incident_time' => now()->subDays(180)->setTime(22, 0),
            'severity' => 5,
            'category' => 'violence',
            'status' => 'verified',
        ]);
        $staleReport->setRelation('user', $user);

        $service = app(SafetyScoreService::class);

        $freshResult = $service->compute($venue, collect([$freshReport]), 'night');
        $staleResult = $service->compute($venue, collect([$staleReport]), 'night');

        // Fresh report has high risk (~5.0) and severely drops score
        $this->assertGreaterThan(4.5, $freshResult['risk']);

        // 180-day old report has decayed to near zero risk (< 0.05)
        $this->assertLessThan(0.05, $staleResult['risk']);
        $this->assertGreaterThan($freshResult['score'], $staleResult['score']);
    }

    public function test_duplicate_reports_do_not_multiply_risk_independently(): void
    {
        $freezeTime = now()->setTime(22, 0);
        $this->travelTo($freezeTime);

        try {
            $venue = $this->makeVenue();
            $user = $this->userWithRole('user', ['trust_score' => 1.0]);

            $originalReport = new Report([
                'id' => 1,
                'incident_time' => $freezeTime->copy(),
                'severity' => 4,
                'category' => 'theft',
                'status' => 'verified',
                'duplicate_of' => null,
                'duplicates_count' => 3, // 3 corroborating duplicates
            ]);
            $originalReport->setRelation('user', $user);

            $duplicateReport = new Report([
                'id' => 2,
                'incident_time' => $freezeTime->copy(),
                'severity' => 4,
                'category' => 'theft',
                'status' => 'verified',
                'duplicate_of' => 1, // marked as duplicate
            ]);
            $duplicateReport->setRelation('user', $user);

            $service = app(SafetyScoreService::class);

            // Duplicate report is skipped in loop, only boost applies to original
            $result = $service->compute($venue, collect([$originalReport, $duplicateReport]), 'night');

            $this->assertSame(1, $result['count']); // Only original report counted
            // Risk = 4 * 1.0 * 1.0 * (1 + 0.25 * 3) = 4 * 1.75 = 7.0
            $this->assertEqualsWithDelta(7.0, $result['risk'], 0.1);
        } finally {
            $this->travelBack();
        }
    }

    public function test_extreme_low_and_high_inputs_stay_strictly_clamped_between_0_and_100(): void
    {
        // 1. Extreme worst-case venue with many severe violent incidents
        $terribleVenue = $this->makeVenue([
            'has_cctv' => false,
            'has_guard' => false,
            'lighting' => 1,
        ]);
        $user = $this->userWithRole('user', ['trust_score' => 1.0]);

        $severeReports = collect();
        for ($i = 0; $i < 20; $i++) {
            $r = new Report([
                'incident_time' => now()->setTime(2, 0),
                'severity' => 5,
                'category' => 'violence',
                'status' => 'verified',
            ]);
            $r->setRelation('user', $user);
            $severeReports->push($r);
        }

        $service = app(SafetyScoreService::class);
        $worstResult = $service->compute($terribleVenue, $severeReports, 'late');

        // Score must not drop below 0
        $this->assertGreaterThanOrEqual(0, $worstResult['score']);
        $this->assertSame(0, $worstResult['score']);
        $this->assertSame('caution', $worstResult['badge']);

        // 2. Best-case venue: max features, daytime, positive reviews
        $greatVenue = $this->makeVenue([
            'has_cctv' => true,
            'has_guard' => true,
            'lighting' => 5,
        ]);
        $positiveReports = collect();
        for ($i = 0; $i < 10; $i++) {
            $r = new Report([
                'incident_time' => now()->setTime(12, 0),
                'category' => 'positive',
                'status' => 'verified',
            ]);
            $r->setRelation('user', $user);
            $positiveReports->push($r);
        }

        $bestResult = $service->compute($greatVenue, $positiveReports, 'day');

        // Score must not exceed 100
        $this->assertLessThanOrEqual(100, $bestResult['score']);
        $this->assertSame(100, $bestResult['score']);
        $this->assertSame('safe', $bestResult['badge']);
    }

    public function test_reporter_trust_score_scales_incident_weight_proportionally(): void
    {
        $freezeTime = Carbon::parse('2026-10-29 21:00:00');
        $this->travelTo($freezeTime);

        try {
            $venue = $this->makeVenue();
            $highTrustUser = $this->userWithRole('user', ['trust_score' => 1.0]);
            $lowTrustUser = $this->userWithRole('user', ['trust_score' => 0.2]);

            $reportHigh = new Report([
                'incident_time' => $freezeTime->copy(),
                'severity' => 4,
                'category' => 'theft',
                'status' => 'verified',
            ]);
            $reportHigh->setRelation('user', $highTrustUser);

            $reportLow = new Report([
                'incident_time' => $freezeTime->copy(),
                'severity' => 4,
                'category' => 'theft',
                'status' => 'verified',
            ]);
            $reportLow->setRelation('user', $lowTrustUser);

            $service = app(SafetyScoreService::class);

            $resHigh = $service->compute($venue, collect([$reportHigh]), 'evening');
            $resLow = $service->compute($venue, collect([$reportLow]), 'evening');

            // High trust user produces 5x the risk penalty of 0.2 trust user (4.0 vs 0.8)
            $this->assertEquals(4.0, $resHigh['risk']);
            $this->assertEquals(0.8, $resLow['risk']);
            $this->assertLessThan($resLow['score'], $resHigh['score']);
        } finally {
            $this->travelBack();
        }
    }
}
