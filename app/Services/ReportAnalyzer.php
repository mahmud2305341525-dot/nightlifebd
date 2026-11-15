<?php

namespace App\Services;

use App\Models\Report;

/**
 * The AI core: turns a raw Bangla / English / Banglish report into
 * category + severity + spam probability + summary + embedding, then looks for duplicates.
 */
class ReportAnalyzer
{
    public function __construct(private GeminiService $gemini, private LocalAnalyzer $local)
    {
    }

    public function analyze(Report $report): Report
    {
        $result = null;
        $source = 'keyword';
        $aiFailed = false;

        if ($this->gemini->enabled()) {
            $result = $this->viaGemini($report->body);
            if ($result) {
                $source = 'gemini';
            } else {
                $aiFailed = true; // quota / network problem -> keyword fallback + flag for manual review
            }
        }
        if (! $result) {
            $result = $this->local->analyze($report->body);
        }

        $emb = $this->embedding($report->body);

        $report->fill([
            'category' => $result['category'],
            'severity' => $result['severity'],
            'language' => $result['language'],
            'spam_probability' => $result['spam_probability'],
            'summary_en' => $result['summary_en'],
            'embedding' => $emb['vector'],
            'embedding_source' => $emb['source'],
            'analysis_source' => $source,
            'needs_review' => $aiFailed || $result['spam_probability'] >= 0.6 || $result['category'] === 'other',
        ])->save();

        $this->markDuplicate($report);

        return $report->fresh();
    }

    /** @return array{vector:array,source:string} */
    public function embedding(string $text): array
    {
        $vec = $this->gemini->embed($text);
        if ($vec) {
            return ['vector' => $vec, 'source' => 'gemini'];
        }

        return ['vector' => $this->local->embed($text), 'source' => 'local'];
    }

    private function viaGemini(string $text): ?array
    {
        $cats = implode('|', Report::CATEGORIES);
        $prompt = <<<P
You analyse night-safety reports from Bangladesh (Dhaka). The text may be Bangla, English or Banglish (Bangla written in English letters).
Return ONLY a JSON object with exactly these keys:
{"category":"{$cats}","severity":1-5,"language":"bn|en|mixed","spam_probability":0-1,"summary_en":"one short English sentence","hour_hint":null or 0-23}
Rules: severity 5 = weapon/violence, 4 = theft or sexual harassment, 2-3 = dark road/overcharging/minor, 1 = positive review.
"positive" means a good, safe experience. spam_probability is high for ads, links, gibberish or text unrelated to safety.
Report: """{$text}"""
P;
        $d = $this->gemini->json($prompt);
        if (! $d || ! isset($d['category'])) {
            return null;
        }

        return [
            'category' => in_array($d['category'], Report::CATEGORIES, true) ? $d['category'] : 'other',
            'severity' => max(1, min(5, (int) ($d['severity'] ?? 3))),
            'language' => in_array($d['language'] ?? '', ['bn', 'en', 'mixed'], true) ? $d['language'] : $this->local->language($text),
            'spam_probability' => round(max(0, min(1, (float) ($d['spam_probability'] ?? 0.05))), 2),
            'summary_en' => mb_substr((string) ($d['summary_en'] ?? ''), 0, 240),
            'hour_hint' => isset($d['hour_hint']) && is_numeric($d['hour_hint']) ? (int) $d['hour_hint'] : null,
        ];
    }

    /** Same venue or within 200 m, in the previous 48 h, with a very similar embedding -> corroboration, not spam. */
    public function markDuplicate(Report $report): ?Report
    {
        if (! $report->embedding) {
            return null;
        }
        $threshold = config('nightlife.duplicate_threshold.'.$report->embedding_source, 0.85);
        $coords = $report->coords();

        $candidates = Report::query()
            ->where('id', '!=', $report->id)
            ->where('status', '!=', 'rejected')
            ->where('embedding_source', $report->embedding_source)
            ->whereNotNull('embedding')
            ->where('created_at', '>=', $report->created_at->copy()->subHours(48))
            ->where('created_at', '<=', $report->created_at)
            ->with('venue')
            ->get()
            ->filter(function (Report $c) use ($report, $coords) {
                if ($report->venue_id && $c->venue_id == $report->venue_id) {
                    return true;
                }
                $cc = $c->coords();

                return $coords && $cc && \App\Support\Geo::distance($coords[0], $coords[1], $cc[0], $cc[1]) <= 200;
            });

        $best = null;
        $bestSim = 0.0;
        foreach ($candidates as $c) {
            $sim = LocalAnalyzer::cosine($report->embedding, $c->embedding);
            if ($sim > $bestSim) {
                $bestSim = $sim;
                $best = $c;
            }
        }
        if ($best && $bestSim >= $threshold) {
            $root = $best->duplicate_of ? ($best->original ?? $best) : $best;
            $report->update(['duplicate_of' => $root->id]);

            return $root;
        }

        return null;
    }
}
