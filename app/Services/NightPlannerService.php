<?php

namespace App\Services;

use App\Models\Venue;
use App\Support\Geo;
use App\Support\TimeBucket;
use Illuminate\Support\Collection;

/**
 * AI Night Planner (retrieval-augmented):
 *  1. extract structured filters from free text (Gemini, or a Bangla/English regex fallback),
 *  2. retrieve candidate venues from OUR database (SQL filters + text similarity, never caution venues),
 *  3. let Gemini compose a 2-3 stop itinerary using ONLY those venues, validate it in PHP (budget, ids),
 *  4. fall back to a rule-based itinerary if the AI is unavailable or breaks the rules.
 */
class NightPlannerService
{
    private const AREA_ALIASES = [
        'ধানমন্ডি' => 'Dhanmondi', 'dhanmondi' => 'Dhanmondi', 'dhanmandi' => 'Dhanmondi',
        'গুলশান' => 'Gulshan', 'gulshan' => 'Gulshan', 'বনানী' => 'Banani', 'banani' => 'Banani',
        'উত্তরা' => 'Uttara', 'uttara' => 'Uttara', 'মিরপুর' => 'Mirpur', 'mirpur' => 'Mirpur',
        'মোহাম্মদপুর' => 'Mohammadpur', 'mohammadpur' => 'Mohammadpur',
        'বসুন্ধরা' => 'Bashundhara', 'bashundhara' => 'Bashundhara',
        'মতিঝিল' => 'Motijheel', 'motijheel' => 'Motijheel',
        'পুরান ঢাকা' => 'Old Dhaka', 'old dhaka' => 'Old Dhaka',
    ];

    private const VIBES = [
        'quiet' => ['শান্ত', 'নিরিবিলি', 'quiet', 'calm', 'peaceful', 'shanto', 'chill'],
        'rooftop' => ['ছাদ', 'rooftop', 'roof', 'view', 'skyline'],
        'family' => ['পরিবার', 'ফ্যামিলি', 'family', 'kids'],
        'music' => ['গান', 'মিউজিক', 'music', 'live', 'band'],
        'late-night food' => ['রাতের খাবার', 'বিরিয়ানি', 'biryani', 'late night', 'street food', 'chai', 'চা', 'kebab', 'কাবাব', 'fuchka', 'ফুচকা'],
        'coffee' => ['কফি', 'coffee', 'cafe', 'ক্যাফে'],
    ];

    public function __construct(private GeminiService $gemini, private LocalAnalyzer $local)
    {
    }

    /**
     * @param  array{text?:string,budget?:?int,group?:?int,area?:?string,hour?:?int}  $in
     * @return array{filters:array,plan:?array,candidates:int,source:string,warning:?string}
     */
    public function plan(array $in, bool $adult): array
    {
        $filters = $this->extractFilters($in);
        $bucket = TimeBucket::fromHour($filters['hour']);
        $candidates = $this->retrieve($filters, $bucket, $adult);

        if ($candidates->count() === 0) {
            return ['filters' => $filters, 'plan' => null, 'candidates' => 0, 'source' => 'none', 'warning' => __('No suitable venues found. Try another area or a bigger budget.')];
        }

        $plan = null;
        $source = 'rules';
        if ($this->gemini->enabled()) {
            for ($try = 0; $try < 2 && ! $plan; $try++) {
                $plan = $this->viaGemini($filters, $candidates, $bucket);
            }
            if ($plan) {
                $source = 'gemini';
            }
        }
        if (! $plan) {
            $plan = $this->rulePlan($filters, $candidates);
        }
        if (! $plan || count($plan['stops']) === 0) {
            return ['filters' => $filters, 'plan' => null, 'candidates' => $candidates->count(), 'source' => $source, 'warning' => __('Your budget is too low for any combination of venues. Try raising it.')];
        }

        return ['filters' => $filters, 'plan' => $plan, 'candidates' => $candidates->count(), 'source' => $source, 'warning' => null];
    }

    public function extractFilters(array $in): array
    {
        $text = trim((string) ($in['text'] ?? ''));
        $f = [
            'budget' => $in['budget'] ?? null,
            'group' => $in['group'] ?? null,
            'area' => $in['area'] ?? null,
            'hour' => $in['hour'] ?? null,
            'vibes' => [],
            'text' => $text,
        ];

        if ($text !== '' && $this->gemini->enabled()) {
            $d = $this->gemini->json(<<<P
Extract planning filters from this night-out request (Bangla, English or Banglish). Return ONLY JSON:
{"budget_total_bdt":number|null,"group_size":number|null,"area":"English area name in Dhaka"|null,"vibes":["quiet","rooftop","family","music","late-night food","coffee"],"start_hour":0-23|null}
Request: """{$text}"""
P);
            if ($d) {
                $f['budget'] ??= isset($d['budget_total_bdt']) ? (int) $d['budget_total_bdt'] : null;
                $f['group'] ??= isset($d['group_size']) ? (int) $d['group_size'] : null;
                $f['area'] ??= $d['area'] ?? null;
                $f['hour'] ??= isset($d['start_hour']) ? (int) $d['start_hour'] : null;
                $f['vibes'] = array_values(array_intersect((array) ($d['vibes'] ?? []), array_keys(self::VIBES)));
            }
        }

        // regex fallback fills whatever is still missing (works offline, understands Bangla digits)
        $t = Geo::asciiDigits(mb_strtolower($text));
        if (! $f['group'] && preg_match('/(\d+)\s*(জন|people|persons|person|friends|pax|ppl)/u', $t, $m)) {
            $f['group'] = (int) $m[1];
        }
        if (! $f['budget'] && preg_match('/(\d[\d,]{2,})\s*(টাকা|taka|tk|bdt|৳)?/u', $t, $m)) {
            $n = (int) str_replace(',', '', $m[1]);
            if ($n >= 100) {
                $f['budget'] = $n;
            }
        }
        if (! $f['area']) {
            foreach (self::AREA_ALIASES as $alias => $en) {
                if (mb_strpos($t, mb_strtolower($alias)) !== false) {
                    $f['area'] = $en;
                    break;
                }
            }
        }
        if (! $f['vibes']) {
            foreach (self::VIBES as $vibe => $words) {
                foreach ($words as $w) {
                    if (mb_strpos($t, mb_strtolower($w)) !== false) {
                        $f['vibes'][] = $vibe;
                        break;
                    }
                }
            }
        }
        $f['group'] = max(1, min(20, (int) ($f['group'] ?: 2)));
        $f['budget'] = $f['budget'] ? (int) $f['budget'] : null;
        $f['hour'] = $f['hour'] !== null ? ((int) $f['hour'] % 24) : 20;

        return $f;
    }

    /** @return Collection<int,Venue> best matches first (max 12) */
    public function retrieve(array $f, string $bucket, bool $adult): Collection
    {
        $venues = Venue::approved()
            ->with('scores')
            ->when(! $adult, fn ($q) => $q->where('is_restricted', false))
            ->when($f['area'], fn ($q, $a) => $q->where('area', 'like', $a))
            ->get()
            ->reject(fn (Venue $v) => $v->badgeFor($bucket) === 'caution');

        $query = $local = $f['text'].' '.implode(' ', $f['vibes']);
        $qv = $this->local->embed($query);

        return $venues
            ->map(function (Venue $v) use ($qv, $bucket) {
                $sim = LocalAnalyzer::cosine($qv, $this->local->embed($v->searchText()));
                $safety = ($v->scoreFor($bucket)?->score ?? 50) / 100;
                $v->setAttribute('rank', $sim + 0.4 * $safety);

                return $v;
            })
            ->sortByDesc('rank')
            ->take(12)
            ->values();
    }

    public function stopCost(Venue $v, int $group): int
    {
        return (int) (config('nightlife.tier_cost.'.$v->price_tier, 700) * $group);
    }

    private function viaGemini(array $f, Collection $cands, string $bucket): ?array
    {
        $list = $cands->map(fn (Venue $v) => [
            'id' => $v->id, 'name' => $v->name, 'category' => $v->category, 'area' => $v->area,
            'price_per_person_bdt' => config('nightlife.tier_cost.'.$v->price_tier, 700),
            'tags' => $v->vibe_tags, 'safety_score' => $v->scoreFor($bucket)?->score, 'closes_at' => $v->closes_at,
        ])->values()->all();
        $listJson = json_encode($list, JSON_UNESCAPED_UNICODE);
        $budget = $f['budget'] ?? 'no limit';
        $vibes = implode(', ', $f['vibes']);

        $d = $this->gemini->json(<<<P
You plan a safe night out in Dhaka. Use ONLY the venues in this JSON list (never invent venues):
{$listJson}
Group size: {$f['group']}. Total budget (BDT): {$budget}. Start hour: {$f['hour']}. Vibes: {$vibes}. Original request: """{$f['text']}"""
Pick 2 or 3 stops, prefer higher safety_score and venues in the same area, and keep total cost (price_per_person_bdt x group size) within budget.
Return ONLY JSON: {"title":"short title","stops":[{"venue_id":number,"reason":"one short sentence in the user's language"}],"tip":"one safety tip"}
P);
        if (! $d || empty($d['stops']) || ! is_array($d['stops'])) {
            return null;
        }

        $byId = $cands->keyBy('id');
        $stops = [];
        $total = 0;
        foreach (array_slice($d['stops'], 0, 4) as $s) {
            $v = $byId->get((int) ($s['venue_id'] ?? 0));
            if (! $v) {
                return null; // hallucinated venue -> reject, caller retries / falls back
            }
            $cost = $this->stopCost($v, $f['group']);
            $total += $cost;
            $stops[] = ['venue' => $v, 'reason' => (string) ($s['reason'] ?? ''), 'cost' => $cost];
        }
        if ($f['budget'] && $total > $f['budget']) {
            return null;
        }

        return $this->finish($stops, $f, (string) ($d['title'] ?? __('Your safe night plan')), (string) ($d['tip'] ?? ''), $bucket);
    }

    /** Deterministic fallback: pick up to 3 venues of different categories that fit the budget. */
    public function rulePlan(array $f, Collection $cands): ?array
    {
        $bucket = TimeBucket::fromHour($f['hour']);
        $stops = [];
        $total = 0;
        $usedCats = [];
        foreach ($cands as $v) {
            if (count($stops) >= 3) {
                break;
            }
            if (in_array($v->category, $usedCats, true) && $cands->count() > 3) {
                continue;
            }
            $cost = $this->stopCost($v, $f['group']);
            if ($f['budget'] && $total + $cost > $f['budget']) {
                continue;
            }
            $total += $cost;
            $usedCats[] = $v->category;
            $stops[] = ['venue' => $v, 'reason' => __('Good match for your request and a :badge safety rating at this hour.', ['badge' => $v->badgeFor($bucket)]), 'cost' => $cost];
        }
        if (! $stops) {
            return null;
        }

        return $this->finish($stops, $f, __('Your safe night plan'), __('Share a live trip link with a friend before you travel between stops.'), $bucket);
    }

    private function finish(array $stops, array $f, string $title, string $tip, string $bucket): array
    {
        $time = $f['hour'] * 60;
        $out = [];
        $total = 0;
        foreach ($stops as $i => $s) {
            if ($i > 0) {
                $time += 20; // travel
            }
            $out[] = [
                'venue' => $s['venue'],
                'arrive' => sprintf('%02d:%02d', intdiv($time, 60) % 24, $time % 60),
                'reason' => $s['reason'],
                'cost' => $s['cost'],
                'badge' => $s['venue']->badgeFor($bucket),
            ];
            $total += $s['cost'];
            $time += 90;
        }

        return ['title' => $title, 'stops' => $out, 'total_cost' => $total, 'tip' => $tip, 'bucket' => $bucket];
    }
}
