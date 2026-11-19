<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Report;
use App\Models\User;
use App\Models\Venue;
use App\Services\IncidentEvaluationService;
use App\Services\SafetyScoreService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase2SafetyAndEvaluationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confidence_calculation_for_zero_reports(): void
    {
        $venue = $this->makeVenue([
            'name' => 'Quiet Library',
            'category' => 'library',
            'area' => 'Shahbagh',
            'lat' => 23.738,
            'lng' => 90.395,
            'lighting' => 4,
            'has_cctv' => true,
            'has_guard' => true,
            'price_tier' => 1,
        ]);

        $service = app(SafetyScoreService::class);
        $confidence = $service->computeConfidence(collect());

        $this->assertEquals('low', $confidence['level']);
        $this->assertEquals(0, $confidence['evidence_count']);
        $this->assertEquals(0, $confidence['independent_reporters']);
        $this->assertStringContainsString('0 community reports', $confidence['explanation']);
    }

    public function test_confidence_calculation_distinguishes_evidence_and_ignores_duplicates_as_independent(): void
    {
        $venue = $this->makeVenue([
            'name' => 'Metro Station Area',
            'category' => 'transport',
            'area' => 'Mirpur',
            'lat' => 23.804,
            'lng' => 90.366,
            'lighting' => 3,
            'price_tier' => 1,
        ]);

        $authorA = User::factory()->create();
        $authorB = User::factory()->create();

        // Root report by author A
        $root = Report::create([
            'user_id' => $authorA->id,
            'venue_id' => $venue->id,
            'body' => 'Poor lighting near pillar 4',
            'category' => 'dark_road',
            'severity' => 2,
            'status' => 'verified',
            'incident_time' => now()->subDays(5),
        ]);

        // Duplicate report by author B
        $dupe = Report::create([
            'user_id' => $authorB->id,
            'venue_id' => $venue->id,
            'duplicate_of' => $root->id,
            'body' => 'Also saw dark pillar near metro',
            'category' => 'dark_road',
            'severity' => 2,
            'status' => 'verified',
            'incident_time' => now()->subDays(4),
        ]);

        $service = app(SafetyScoreService::class);
        $reports = collect([$root, $dupe]);
        $confidence = $service->computeConfidence($reports);

        // Duplicates are not primary evidence; only 1 independent primary reporter
        $this->assertEquals(1, $confidence['evidence_count']);
        $this->assertEquals(1, $confidence['independent_reporters']);
        $this->assertEquals('low', $confidence['level']);
    }

    public function test_confidence_tiers_scale_with_independent_reporters_and_recency(): void
    {
        $service = app(SafetyScoreService::class);

        // 6 independent reporters with recent reports => moderate confidence
        $moderateReports = collect();
        for ($i = 1; $i <= 6; $i++) {
            $user = User::factory()->create();
            $moderateReports->push(new Report([
                'user_id' => $user->id,
                'category' => 'dark_road',
                'severity' => 2,
                'status' => 'verified',
                'incident_time' => now()->subDays(10),
            ]));
        }

        $moderateConf = $service->computeConfidence($moderateReports);
        $this->assertEquals('moderate', $moderateConf['level']);
        $this->assertEquals(6, $moderateConf['independent_reporters']);
        $this->assertEquals(6, $moderateConf['evidence_count']);

        // 16 independent reporters with recent reports => high confidence
        $highReports = collect();
        for ($i = 1; $i <= 16; $i++) {
            $user = User::factory()->create();
            $highReports->push(new Report([
                'user_id' => $user->id,
                'category' => 'positive',
                'severity' => 1,
                'status' => 'verified',
                'incident_time' => now()->subDays(5),
            ]));
        }

        $highConf = $service->computeConfidence($highReports);
        $this->assertEquals('high', $highConf['level']);
        $this->assertEquals(16, $highConf['independent_reporters']);
        $this->assertEquals(16, $highConf['evidence_count']);
        $this->assertStringContainsString('High confidence', $highConf['explanation']);
    }

    public function test_user_trust_score_clamped_between_0_10_and_0_90(): void
    {
        $user = User::factory()->create();

        // 100 verified reports: raw trust = (100 + 1) / (100 + 0 + 2) = 101/102 ≈ 0.99
        // Must clamp to 0.90 max
        for ($i = 0; $i < 5; $i++) {
            Report::create([
                'user_id' => $user->id,
                'body' => "Verified report {$i}",
                'status' => 'verified',
                'incident_time' => now(),
            ]);
        }

        $user->recalcTrust();
        $user->refresh();
        $this->assertLessThanOrEqual(0.90, $user->trust_score);

        // 50 rejected reports: raw trust drops, must clamp to 0.10 min
        for ($i = 0; $i < 50; $i++) {
            Report::create([
                'user_id' => $user->id,
                'body' => "Spam report {$i}",
                'status' => 'rejected',
                'incident_time' => now(),
            ]);
        }

        $user->recalcTrust();
        $user->refresh();
        $this->assertGreaterThanOrEqual(0.10, $user->trust_score);
    }

    public function test_duplicate_by_same_author_does_not_multiply_risk_boost(): void
    {
        $venue = $this->makeVenue([
            'name' => 'Gulshan Lakeside',
            'category' => 'park',
            'area' => 'Gulshan',
            'lat' => 23.792,
            'lng' => 90.415,
            'lighting' => 3,
            'price_tier' => 2,
        ]);

        $authorA = User::factory()->create(['trust_score' => 0.80]);
        $authorB = User::factory()->create(['trust_score' => 0.80]);

        $root = Report::create([
            'user_id' => $authorA->id,
            'venue_id' => $venue->id,
            'body' => 'Harassment observed',
            'category' => 'harassment',
            'severity' => 4,
            'status' => 'verified',
            'incident_time' => now(),
        ]);

        // Duplicate by SAME author
        $sameAuthorDupe = Report::create([
            'user_id' => $authorA->id,
            'venue_id' => $venue->id,
            'duplicate_of' => $root->id,
            'body' => 'Adding more details on harassment',
            'category' => 'harassment',
            'severity' => 4,
            'status' => 'verified',
            'incident_time' => now(),
        ]);

        $service = app(SafetyScoreService::class);

        // Evaluate with duplicate by same author
        $reportsWithSameAuthorDupe = Report::where('id', $root->id)->with('duplicates')->get();
        $evalSame = $service->compute($venue, $reportsWithSameAuthorDupe, 'night');

        // Now evaluate with duplicate by DIFFERENT author
        $sameAuthorDupe->delete();
        $differentAuthorDupe = Report::create([
            'user_id' => $authorB->id,
            'venue_id' => $venue->id,
            'duplicate_of' => $root->id,
            'body' => 'I can corroborate the harassment here',
            'category' => 'harassment',
            'severity' => 4,
            'status' => 'verified',
            'incident_time' => now(),
        ]);

        $reportsWithDiffAuthorDupe = Report::where('id', $root->id)->with('duplicates')->get();
        $evalDiff = $service->compute($venue, $reportsWithDiffAuthorDupe, 'night');

        // Corroboration by independent user gives higher risk penalty than duplicate by same author
        $this->assertGreaterThan($evalSame['risk'], $evalDiff['risk']);
    }

    public function test_single_reporter_penalty_damping_caps_uncorroborated_risk(): void
    {
        $venue = $this->makeVenue([
            'name' => 'Banani Lounge',
            'category' => 'cafe',
            'area' => 'Banani',
            'lat' => 23.793,
            'lng' => 90.404,
            'lighting' => 5,
            'has_cctv' => true,
            'has_guard' => true,
            'price_tier' => 3,
        ]);

        $soleUser = User::factory()->create(['trust_score' => 0.90]);
        // Set incident time to 22:00 so it falls in 'night' bucket (weight = 1.0)
        $nightTime = Carbon::today()->setHour(22)->setMinute(0);

        // Sole user posts multiple max-severity incidents
        for ($i = 0; $i < 5; $i++) {
            Report::create([
                'user_id' => $soleUser->id,
                'venue_id' => $venue->id,
                'body' => "Severe incident {$i}",
                'category' => 'violence',
                'severity' => 5,
                'status' => 'verified',
                'incident_time' => $nightTime,
            ]);
        }

        $service = app(SafetyScoreService::class);
        $reports = Report::where('venue_id', $venue->id)->with('duplicates')->get();
        $eval = $service->compute($venue, $reports, 'night');

        // Without independent corroboration, risk must be capped at 11.0
        $this->assertEquals(11.0, $eval['risk']);
    }

    public function test_per_user_per_venue_24_hour_cooldown_prevents_spam(): void
    {
        $venue = $this->makeVenue([
            'name' => 'Target Venue Cafe',
            'category' => 'cafe',
            'area' => 'Dhanmondi',
            'lat' => 23.746,
            'lng' => 90.375,
            'lighting' => 4,
            'price_tier' => 2,
        ]);

        $user = User::factory()->create();

        // First report passes
        $response1 = $this->actingAs($user)->post(route('reports.store'), [
            'venue_id' => $venue->id,
            'kind' => 'incident',
            'body' => 'First report: broken streetlight outside entrance.',
        ]);
        $response1->assertRedirect(route('reports.mine'));

        // Immediate second report for SAME venue is rejected by cooldown
        $response2 = $this->actingAs($user)->from(route('reports.create'))->post(route('reports.store'), [
            'venue_id' => $venue->id,
            'kind' => 'incident',
            'body' => 'Second report trying to manipulate score again.',
        ]);
        $response2->assertSessionHasErrors('venue_id');

        // Report for a DIFFERENT venue should be allowed
        $otherVenue = $this->makeVenue([
            'name' => 'Different Venue',
            'category' => 'cafe',
            'area' => 'Gulshan',
            'lat' => 23.79,
            'lng' => 90.41,
            'lighting' => 4,
            'price_tier' => 2,
        ]);

        $response3 = $this->actingAs($user)->post(route('reports.store'), [
            'venue_id' => $otherVenue->id,
            'kind' => 'incident',
            'body' => 'Legitimate report for a completely different place.',
        ]);
        $response3->assertRedirect(route('reports.mine'));
    }

    public function test_velocity_burst_detection_flags_frequent_submissions(): void
    {
        $user = User::factory()->create();

        // Submit 3 reports in quick succession for free-standing locations
        for ($i = 1; $i <= 3; $i++) {
            Report::create([
                'user_id' => $user->id,
                'lat' => 23.75 + ($i * 0.001),
                'lng' => 90.38 + ($i * 0.001),
                'kind' => 'incident',
                'body' => "Location report #{$i} in Dhanmondi area",
                'status' => 'pending',
                'incident_time' => now(),
                'created_at' => now()->subMinutes(2),
            ]);
        }

        // 4th report via controller will trigger burst detection
        $response = $this->actingAs($user)->post(route('reports.store'), [
            'lat' => 23.755,
            'lng' => 90.385,
            'kind' => 'incident',
            'body' => 'Fourth rapid report: sudden velocity burst triggered.',
        ]);
        $response->assertRedirect(route('reports.mine'));

        $fourthReport = Report::where('user_id', $user->id)->latest('id')->first();
        $this->assertTrue((bool) $fourthReport->needs_review);

        // Verify audit log captured velocity flag
        $audit = AuditLog::where('user_id', $user->id)
            ->where('action', 'report.submitted')
            ->latest('id')
            ->first();
        $this->assertNotNull($audit);
        $this->assertTrue($audit->meta['velocity_flag']);
    }

    public function test_incident_evaluation_service_computes_accuracy_confusion_matrix_and_f1(): void
    {
        $user = User::factory()->create();

        // Report 1: AI predicted harassment, Moderator confirmed harassment (TP for harassment)
        Report::create([
            'user_id' => $user->id,
            'body' => 'Verbal harassment near entrance',
            'category' => 'harassment',
            'severity' => 3,
            'ai_category' => 'harassment',
            'ai_severity' => 3,
            'analysis_source' => 'gemini',
            'status' => 'verified',
            'incident_time' => now(),
        ]);

        // Report 2: AI predicted dark_road, Moderator decided harassment (Misclassification)
        Report::create([
            'user_id' => $user->id,
            'body' => 'Dark alley harassment',
            'category' => 'harassment',
            'severity' => 3,
            'ai_category' => 'dark_road',
            'ai_severity' => 3,
            'analysis_source' => 'keyword',
            'status' => 'verified',
            'incident_time' => now(),
        ]);

        $evalService = app(IncidentEvaluationService::class);
        $results = $evalService->evaluateProductionDecisions();

        $this->assertEquals(2, $results['total_moderated']);
        $this->assertEquals(0.5, $results['overall_accuracy']);
        $this->assertEquals(1.0, $results['severity_exact_match']);

        // Check confusion matrix: [actual][predicted]
        $matrix = $results['confusion_matrix'];
        $this->assertEquals(1, $matrix['harassment']['harassment']); // TP
        $this->assertEquals(1, $matrix['harassment']['dark_road']);   // Actual harassment, predicted dark_road

        // Check source breakdown
        $this->assertEquals(1, $results['source_breakdown']['gemini']['count']);
        $this->assertEquals(1.0, $results['source_breakdown']['gemini']['accuracy']);
        $this->assertEquals(1, $results['source_breakdown']['keyword']['count']);
        $this->assertEquals(0.0, $results['source_breakdown']['keyword']['accuracy']);
    }

    public function test_admin_dashboard_displays_ai_evaluation_dashboard(): void
    {
        $admin = $this->userWithRole('safety_admin');

        Report::create([
            'user_id' => $admin->id,
            'body' => 'Test harassment incident for admin view',
            'category' => 'harassment',
            'severity' => 3,
            'ai_category' => 'harassment',
            'ai_severity' => 3,
            'analysis_source' => 'gemini',
            'status' => 'verified',
            'incident_time' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));
        $response->assertOk();
        $response->assertSee('AI vs. Moderator Evaluation Dashboard');
        $response->assertSee('Category Accuracy');
        $response->assertSee('Macro F1-Score');
        $response->assertSee('Confusion Matrix');
        $response->assertSee('Gemini AI vs. Local Lexicon');
    }
}
