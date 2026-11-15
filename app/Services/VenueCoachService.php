<?php

namespace App\Services;

use App\Models\Report;
use App\Models\Venue;

/** Venue Safety Coach: turns verified community reports into 3 concrete improvement tips for the owner. */
class VenueCoachService
{
    public function __construct(private GeminiService $gemini)
    {
    }

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
Give exactly 3 specific, practical improvement tips for the owner. Return ONLY JSON: {"tips":["...","...","..."]}
P);
            if ($d && ! empty($d['tips']) && is_array($d['tips'])) {
                $tips = array_slice(array_map('strval', $d['tips']), 0, 3);
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
            $tips[] = __('Station a trained guard at the entrance and the parking area during night hours.');
        }
        if ($v->lighting <= 3 || ($cats['dark_road'] ?? 0) > 0) {
            $tips[] = __('Improve lighting at the entrance, back door and the approach road; reported dark spots lower your night score.');
        }
        if (! $v->has_cctv || ($cats['theft'] ?? 0) > 0) {
            $tips[] = __('Install visible CCTV covering the entrance and parking, and display a sign saying it is monitored.');
        }
        if (($cats['overcharging'] ?? 0) > 0) {
            $tips[] = __('Print clear prices and help guests book metered transport to prevent overcharging complaints.');
        }
        $tips[] = __('Train staff to respond to harassment complaints and share your emergency contact numbers with guests.');
        $tips[] = __('Reply to community reports through the partner portal so guests see you are acting on feedback.');

        return array_slice(array_values(array_unique($tips)), 0, 3);
    }

    private function yn(bool $b): string
    {
        return $b ? 'yes' : 'no';
    }
}
