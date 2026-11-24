<?php

namespace App\Services;

use App\Models\Report;
use App\Models\Venue;

/** Venue Safety Coach: turns verified community reports into 3 concrete improvement tips for the owner. */
class VenueCoachService
{
    public function __construct(private GeminiService $gemini) {}

    /** Cached per venue for one day. */
    public function tips(Venue $venue, bool $force = false): array
    {
        if (! $force && $venue->coach_at && $venue->coach_at->isToday() && $venue->coach_tips) {
            return $venue->coach_tips;
        }

        $reports = Report::verified()->where('venue_id', $venue->id)
            ->where('incident_time', '>=', now()->subDays(90))->latest('incident_time')->limit(25)->get();

        $tips = null;
        if ($reports->isNotEmpty() && $this->gemini->enabled()) {
            $summaries = $reports->map(fn ($r) => '- ['.$r->category.', severity '.$r->severity.'] '.($r->summary_en ?: mb_substr($r->body, 0, 140)))->implode("\n");
            $d = $this->gemini->json(<<<P
You are a venue safety coach in Bangladesh. Venue: {$venue->name} ({$venue->category}, {$venue->area}).
Checklist: CCTV={$this->yn($venue->has_cctv)}, guard={$this->yn($venue->has_guard)}, lighting={$venue->lighting}/5.
Verified community reports (last 90 days):
{$summaries}
Give exactly 3 specific, practical improvement tips with actionable ROI for the owner. Return ONLY JSON:
{"tips":[{"title":"...","detail":"...","impact":"+X pts","category":"Guard|Lighting|CCTV|Operations","cost":"Low|Medium|Free"}]}
P);
            if ($d && ! empty($d['tips']) && is_array($d['tips'])) {
                $tips = array_slice($d['tips'], 0, 3);
            }
        }
        $tips ??= $this->ruleTips($venue, $reports);

        $venue->update(['coach_tips' => $tips, 'coach_at' => now()]);

        return $tips;
    }

    public function ruleTips(Venue $v, $reports): array
    {
        $tips = [];
        $cats = $reports->pluck('category')->countBy();
        if (! $v->has_guard || ($cats['harassment'] ?? 0) + ($cats['violence'] ?? 0) > 0) {
            $tips[] = [
                'title' => __('Station a dedicated security guard at entrance & parking'),
                'detail' => __('Active physical security directly deters harassers and satisfies the safety checklist requirement.'),
                'impact' => '+20 pts Base Boost',
                'category' => 'Guard',
                'cost' => 'Medium (Night Shift)',
            ];
        }
        if ($v->lighting <= 3 || ($cats['dark_road'] ?? 0) > 0) {
            $tips[] = [
                'title' => __('Upgrade approach road & parking to bright 50W LED floodlights'),
                'detail' => __('Eliminates dark spot penalties and raises your venue lighting rating from :cur/5 to 5/5.', ['cur' => $v->lighting]),
                'impact' => '+8 to +12 pts',
                'category' => 'Lighting',
                'cost' => 'Low (৳2,500 - ৳4,000)',
            ];
        }
        if (! $v->has_cctv || ($cats['theft'] ?? 0) > 0) {
            $tips[] = [
                'title' => __('Mount outdoor night-vision CCTV with a visible "Under Surveillance" sign'),
                'detail' => __('Directly satisfies the CCTV verification metric (+20 checklist points) and discourages snatchers.'),
                'impact' => '+20 pts Base Boost',
                'category' => 'CCTV',
                'cost' => 'Low-Medium (৳3,500 - ৳5,500)',
            ];
        }
        if (($cats['overcharging'] ?? 0) > 0) {
            $tips[] = [
                'title' => __('Display transparent pricing and assist late-night guests with metered transport'),
                'detail' => __('Prevents negative overcharging and harassment claims when guests leave after midnight.'),
                'impact' => '+5 pts',
                'category' => 'Operations',
                'cost' => 'Free (Staff Protocol)',
            ];
        }
        $tips[] = [
            'title' => __('Brief staff on emergency escalation, 999 dialing, and safe taxi assistance'),
            'detail' => __('Equips staff to intervene early during disturbances and safeguard female patrons.'),
            'impact' => '+5 pts',
            'category' => 'Staff Training',
            'cost' => 'Free (Internal)',
        ];

        return array_slice($tips, 0, 3);
    }

    private function yn(?bool $b): string
    {
        return $b ? 'yes' : 'no';
    }
}
