<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Services\SafetyScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModerationAndScoreTest extends TestCase
{
    use RefreshDatabase;

    private function theftReport($user, $venue, string $status = 'verified'): Report
    {
        return Report::create([
            'user_id' => $user->id, 'venue_id' => $venue->id, 'body' => 'phone snatched at night', 'incident_time' => now()->subDay()->setTime(23, 0),
            'status' => $status, 'category' => 'theft', 'severity' => 4, 'analysis_source' => 'seed',
        ]);
    }

    public function test_score_is_time_aware_and_explainable(): void
    {
        $v = $this->makeVenue();
        $u = $this->userWithRole('user', ['trust_score' => 0.9]);
        foreach (range(1, 3) as $_) {
            $this->theftReport($u, $v);
        }
        app(SafetyScoreService::class)->recalcVenue($v);
        $v = $v->fresh()->load('scores');

        $this->assertSame('caution', $v->badgeFor('night'));
        $this->assertSame('safe', $v->badgeFor('day'));
        $this->assertStringContainsString('theft', strtolower($v->scoreFor('night')->explanation));
    }

    public function test_unverified_reports_do_not_change_the_score(): void
    {
        $v = $this->makeVenue();
        $u = $this->userWithRole('user', ['trust_score' => 0.9]);
        $this->theftReport($u, $v, 'pending');
        app(SafetyScoreService::class)->recalcVenue($v);
        $this->assertSame('safe', $v->fresh()->load('scores')->badgeFor('night'));
    }

    public function test_verifying_a_report_updates_trust_and_score(): void
    {
        $v = $this->makeVenue();
        $reporter = $this->userWithRole('user');
        $admin = $this->userWithRole('safety_admin');
        $r = $this->theftReport($reporter, $v, 'pending');

        $this->actingAs($admin)->post('/admin/reports/'.$r->id.'/verify')->assertRedirect();

        $this->assertSame('verified', $r->fresh()->status);
        $this->assertEqualsWithDelta(0.67, (float) $reporter->fresh()->trust_score, 0.01); // (1+1)/(1+0+2)
        $this->assertDatabaseHas('audit_logs', ['action' => 'report.verified']);
        $this->assertNotNull($v->fresh()->load('scores')->scoreFor('night'));
    }

    public function test_rejecting_lowers_reporter_trust(): void
    {
        $v = $this->makeVenue();
        $reporter = $this->userWithRole('user');
        $admin = $this->userWithRole('safety_admin');
        $r = $this->theftReport($reporter, $v, 'pending');
        $this->actingAs($admin)->post('/admin/reports/'.$r->id.'/reject');
        $this->assertEqualsWithDelta(0.33, (float) $reporter->fresh()->trust_score, 0.01); // (0+1)/(0+1+2)
    }
}
