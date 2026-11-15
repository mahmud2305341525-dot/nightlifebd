<?php

namespace App\Services;

use App\Models\Report;
use App\Models\Venue;
use App\Models\VenueScore;
use App\Support\Geo;
use App\Support\TimeBucket;
use Illuminate\Support\Collection;

/**
 * Explainable, time-aware safety score.
 *
 *   base  = 40 + 20 (CCTV) + 20 (guard) + 4 x lighting          (venue checklist, "A_status")
 *   risk  = sum( severity x reporter_trust x e^(-age/30d) x time_weight x duplicate_boost )   ("U_reports")
 *   score = clamp(base + bucket_adjust - 5 x risk, 0, 100)
 */
class SafetyScoreService
{
    private const BUCKET_ADJUST = ['day' => 10, 'evening' => 0, 'night' => -5, 'late' => -10];

    public function recalcAll(): int
    {
        $n = 0;
        Venue::approved()->get()->each(function (Venue $v) use (&$n) {
            $this->recalcVenue($v);
            $n++;
        });

        return $n;
    }

    /** Recalculate the venue a report belongs to, plus any venue within 150 m of a free-standing report. */
    public function recalcAffectedBy(Report $report): void
    {
        $venues = collect();
        if ($report->venue) {
            $venues->push($report->venue);
        }
        if ($report->lat !== null && $report->lng !== null) {
            Venue::approved()
                ->whereBetween('lat', [$report->lat - 0.002, $report->lat + 0.002])
                ->whereBetween('lng', [$report->lng - 0.002, $report->lng + 0.002])
                ->get()
                ->each(function (Venue $v) use ($report, $venues) {
                    if (Geo::distance($report->lat, $report->lng, $v->lat, $v->lng) <= 150) {
                        $venues->push($v);
                    }
                });
        }
        $venues->unique('id')->each(fn (Venue $v) => $this->recalcVenue($v));
    }

    public function recalcVenue(Venue $v): void
    {
        $reports = $this->reportsFor($v);
        $scores = [];
        foreach (TimeBucket::ALL as $bucket) {
            $r = $this->compute($v, $reports, $bucket);
            $scores[$bucket] = $r;
            VenueScore::updateOrCreate(
                ['venue_id' => $v->id, 'bucket' => $bucket],
                ['score' => $r['score'], 'badge' => $r['badge'], 'explanation' => $r['explanation'], 'report_count' => $r['count']]
            );
        }
        $v->update(['safety_score' => $scores['night']['score'], 'badge' => $scores['night']['badge']]);
    }

    /** Verified reports tied to this venue, or free-standing reports within 150 m of it. */
    public function reportsFor(Venue $v): Collection
    {
        return Report::query()
            ->verified()
            ->with('user:id,trust_score')
            ->withCount('duplicates')
            ->where(function ($q) use ($v) {
                $q->where('venue_id', $v->id)->orWhere(function ($q2) use ($v) {
                    $q2->whereNull('venue_id')
                        ->whereBetween('lat', [$v->lat - 0.002, $v->lat + 0.002])
                        ->whereBetween('lng', [$v->lng - 0.002, $v->lng + 0.002]);
                });
            })
            ->get()
            ->filter(fn (Report $r) => $r->venue_id == $v->id
                || ($r->lat !== null && Geo::distance($r->lat, $r->lng, $v->lat, $v->lng) <= 150))
            ->values();
    }

    /** @return array{score:int,badge:string,explanation:string,count:int,risk:float} */
    public function compute(Venue $v, Collection $reports, string $bucket): array
    {
        $risk = 0.0;
        $cats = [];
        $positives = 0;
        $count = 0;

        foreach ($reports as $r) {
            if ($r->duplicate_of) {
                continue; // corroboration is counted on the original report, not as new risk
            }
            $count++;
            $ageDays = max(0, (now()->timestamp - $r->incident_time->timestamp) / 86400);
            $decay = exp(-$ageDays / 30);
            $w = $r->bucket() === $bucket ? 1.0 : 0.3;
            $trust = (float) ($r->user->trust_score ?? 0.5);

            if ($r->category === 'positive') {
                $risk -= 0.5 * $trust * $decay * $w;
                $positives++;

                continue;
            }
            $boost = 1 + 0.25 * min(3, (int) $r->duplicates_count);
            $risk += ((int) $r->severity) * $trust * $decay * $w * $boost;
            if ($w === 1.0 && $ageDays <= 30) {
                $cats[$r->category ?? 'other'] = ($cats[$r->category ?? 'other'] ?? 0) + 1;
            }
        }
        $risk = max(-3.0, $risk);

        $base = 40 + ($v->has_cctv ? 20 : 0) + ($v->has_guard ? 20 : 0) + $v->lighting * 4;
        $score = (int) round(max(0, min(100, $base + self::BUCKET_ADJUST[$bucket] - $risk * 5)));
        $badge = $score >= 70 ? 'safe' : ($score >= 45 ? 'moderate' : 'caution');

        return [
            'score' => $score,
            'badge' => $badge,
            'count' => $count,
            'risk' => round($risk, 2),
            'explanation' => $this->explain($v, $badge, $bucket, $cats, $positives, $count),
        ];
    }

    private function explain(Venue $v, string $badge, string $bucket, array $cats, int $positives, int $count): string
    {
        $parts = [];
        foreach ($cats as $cat => $n) {
            $parts[] = $n.' verified '.strtolower(Report::categoryLabel($cat)).' report'.($n > 1 ? 's' : '').' in 30 days';
        }
        if (! $parts) {
            $parts[] = 'no verified incident reports for this time of day';
        }
        $missing = [];
        if (! $v->has_guard) {
            $missing[] = 'no security guard';
        }
        if (! $v->has_cctv) {
            $missing[] = 'no CCTV';
        }
        if ($v->lighting <= 2) {
            $missing[] = 'poor lighting';
        }
        $text = ucfirst($badge).' ('.strtolower(TimeBucket::label($bucket)).'): '.implode('; ', $parts);
        if ($missing) {
            $text .= '; '.implode(', ', $missing);
        }
        if ($positives) {
            $text .= '; '.$positives.' positive review'.($positives > 1 ? 's' : '');
        }
        $text .= '.';
        if ($count === 0) {
            $text .= ' Based on the venue checklist only (limited community data).';
        }

        return $text;
    }
}
