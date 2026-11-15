<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Services\ReportAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_body_is_limited_to_500_characters(): void
    {
        $user = $this->userWithRole('user');
        $v = $this->makeVenue();
        $this->actingAs($user)->post('/reports', ['kind' => 'incident', 'body' => str_repeat('a', 501), 'venue_id' => $v->id])->assertSessionHasErrors('body');
    }

    public function test_report_needs_a_venue_or_a_map_point(): void
    {
        $user = $this->userWithRole('user');
        $this->actingAs($user)->post('/reports', ['kind' => 'incident', 'body' => 'Street was very dark and empty'])->assertSessionHasErrors('venue_id');
    }

    public function test_report_is_saved_as_pending(): void
    {
        $user = $this->userWithRole('user');
        $v = $this->makeVenue();
        $this->actingAs($user)->post('/reports', ['kind' => 'incident', 'body' => 'ছিনতাই হয়েছে রাত ১১টায় গেটের সামনে', 'venue_id' => $v->id])->assertRedirect('/my-reports');
        $r = Report::first();
        $this->assertNotNull($r);
        $this->assertSame('pending', $r->status);
    }

    public function test_analyzer_classifies_and_detects_duplicates_offline(): void
    {
        $user = $this->userWithRole('user');
        $v = $this->makeVenue();
        $mk = fn () => Report::create(['user_id' => $user->id, 'venue_id' => $v->id, 'body' => 'Someone snatched my phone near the entrance at night', 'incident_time' => now()->subHour()]);
        $a = $mk();
        $b = $mk();

        $analyzer = app(ReportAnalyzer::class);
        $a = $analyzer->analyze($a);
        $b = $analyzer->analyze($b);

        $this->assertSame('theft', $a->category);
        $this->assertGreaterThanOrEqual(4, $a->severity);
        $this->assertSame('keyword', $a->analysis_source);
        $this->assertNull($a->duplicate_of);
        $this->assertSame($a->id, $b->duplicate_of, 'identical report must be flagged as duplicate of the first');
    }
}
