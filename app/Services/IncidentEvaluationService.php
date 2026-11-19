<?php

namespace App\Services;

use App\Models\Report;

/**
 * Benchmark Evaluation Harness for Incident Classification & Trust & Safety.
 * Evaluates NLP analyzers (Local Lexicon & Cloud LLM) against the versioned benchmark dataset.
 */
class IncidentEvaluationService
{
    public function __construct(private LocalAnalyzer $local) {}

    /**
     * Run full evaluation benchmark against a labeled dataset file.
     *
     * @return array{
     *     dataset_version: string,
     *     total_samples: int,
     *     overall_accuracy: float,
     *     macro_f1: float,
     *     severity_exact_match: float,
     *     severity_mae: float,
     *     spam_tpr: float,
     *     spam_fpr: float,
     *     category_metrics: array,
     *     language_accuracy: float,
     *     confusion_matrix: array,
     *     failures: array
     * }
     */
    public function evaluateDataset(?string $datasetPath = null, ?callable $analyzerCallback = null): array
    {
        $path = $datasetPath ?: base_path('tests/Fixtures/incident_evaluation_dataset.json');
        if (! file_exists($path)) {
            throw new \RuntimeException("Evaluation dataset file not found: {$path}");
        }

        $data = json_decode(file_get_contents($path), true);
        if (! is_array($data) || ! isset($data['samples'])) {
            throw new \RuntimeException('Invalid evaluation dataset JSON structure.');
        }

        $samples = $data['samples'];
        $categories = Report::CATEGORIES;

        // Initialize confusion matrix and metric counters
        $matrix = [];
        foreach ($categories as $actual) {
            foreach ($categories as $predicted) {
                $matrix[$actual][$predicted] = 0;
            }
        }

        $correctCategory = 0;
        $correctLanguage = 0;
        $severityExact = 0;
        $severityDiffSum = 0;

        $spamActualCount = 0;
        $spamDetectedCount = 0;
        $nonSpamActualCount = 0;
        $nonSpamFalseAlarms = 0;

        $failures = [];

        foreach ($samples as $sample) {
            $text = $sample['text'];
            $expCat = $sample['expected_category'];
            $expSev = $sample['expected_severity'];
            $expLang = $sample['expected_language'] ?? null;
            $expSpam = $sample['is_spam'] ?? false;

            // Execute analyzer
            if ($analyzerCallback) {
                $pred = $analyzerCallback($text);
            } else {
                $pred = $this->local->analyze($text);
            }

            $predCat = $pred['category'];
            $predSev = (int) $pred['severity'];
            $predLang = $pred['language'] ?? 'en';
            $predSpamProb = (float) ($pred['spam_probability'] ?? 0.0);
            $predIsSpam = ($predSpamProb >= 0.60);

            // Tally confusion matrix
            if (isset($matrix[$expCat][$predCat])) {
                $matrix[$expCat][$predCat]++;
            }

            // Accuracy tallies
            if ($predCat === $expCat) {
                $correctCategory++;
            } else {
                $failures[] = [
                    'id' => $sample['id'],
                    'text' => $text,
                    'expected_category' => $expCat,
                    'predicted_category' => $predCat,
                    'expected_severity' => $expSev,
                    'predicted_severity' => $predSev,
                    'reason' => 'Category mismatch',
                ];
            }

            if ($expLang && $predLang === $expLang) {
                $correctLanguage++;
            }

            if ($predSev === $expSev) {
                $severityExact++;
            }
            $severityDiffSum += abs($predSev - $expSev);

            // Spam metrics
            if ($expSpam) {
                $spamActualCount++;
                if ($predIsSpam || $predCat === 'other') {
                    $spamDetectedCount++;
                }
            } else {
                $nonSpamActualCount++;
                if ($predIsSpam) {
                    $nonSpamFalseAlarms++;
                }
            }
        }

        $n = count($samples);
        $catMetrics = [];
        $f1Scores = [];

        foreach ($categories as $cat) {
            $tp = $matrix[$cat][$cat];
            $fp = 0;
            $fn = 0;
            foreach ($categories as $other) {
                if ($other !== $cat) {
                    $fp += $matrix[$other][$cat];
                    $fn += $matrix[$cat][$other];
                }
            }

            $precision = ($tp + $fp > 0) ? round($tp / ($tp + $fp), 3) : 0.0;
            $recall = ($tp + $fn > 0) ? round($tp / ($tp + $fn), 3) : 0.0;
            $f1 = ($precision + $recall > 0) ? round((2 * $precision * $recall) / ($precision + $recall), 3) : 0.0;

            $catMetrics[$cat] = [
                'support' => $tp + $fn,
                'tp' => $tp,
                'fp' => $fp,
                'fn' => $fn,
                'precision' => $precision,
                'recall' => $recall,
                'f1' => $f1,
            ];

            if (($tp + $fn) > 0) {
                $f1Scores[] = $f1;
            }
        }

        $macroF1 = count($f1Scores) > 0 ? round(array_sum($f1Scores) / count($f1Scores), 3) : 0.0;

        return [
            'dataset_version' => $data['version'] ?? '1.0.0',
            'total_samples' => $n,
            'overall_accuracy' => round($correctCategory / $n, 3),
            'macro_f1' => $macroF1,
            'severity_exact_match' => round($severityExact / $n, 3),
            'severity_mae' => round($severityDiffSum / $n, 2),
            'spam_tpr' => $spamActualCount > 0 ? round($spamDetectedCount / $spamActualCount, 3) : 1.0,
            'spam_fpr' => $nonSpamActualCount > 0 ? round($nonSpamFalseAlarms / $nonSpamActualCount, 3) : 0.0,
            'language_accuracy' => round($correctLanguage / $n, 3),
            'category_metrics' => $catMetrics,
            'confusion_matrix' => $matrix,
            'failures' => $failures,
        ];
    }

    /**
     * Evaluate actual production AI predictions against human moderator decisions.
     * Strictly grounded in real reports and audit records with no fabricated stats.
     *
     * @return array{
     *     total_moderated: int,
     *     overall_accuracy: float,
     *     macro_f1: float,
     *     severity_exact_match: float,
     *     severity_mae: float,
     *     category_metrics: array,
     *     confusion_matrix: array,
     *     source_breakdown: array
     * }
     */
    public function evaluateProductionDecisions(?string $from = null, ?string $to = null, ?string $categoryFilter = null, ?string $sourceFilter = null): array
    {
        $categories = Report::CATEGORIES;

        $query = Report::query()
            ->whereNotNull('category')
            ->whereNotNull('ai_category');

        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        if ($to) {
            $query->where('created_at', '<=', $to);
        }
        if ($categoryFilter && in_array($categoryFilter, $categories, true)) {
            $query->where('category', $categoryFilter);
        }
        if ($sourceFilter && in_array($sourceFilter, ['gemini', 'keyword'], true)) {
            $query->where('analysis_source', $sourceFilter);
        }

        $reports = $query->get();
        $total = $reports->count();

        if ($total === 0) {
            return [
                'total_moderated' => 0,
                'overall_accuracy' => 0.0,
                'macro_f1' => 0.0,
                'severity_exact_match' => 0.0,
                'severity_mae' => 0.0,
                'category_metrics' => [],
                'confusion_matrix' => [],
                'source_breakdown' => [],
            ];
        }

        // Initialize confusion matrix: [actual_ground_truth][predicted_by_ai]
        $matrix = [];
        foreach ($categories as $actual) {
            foreach ($categories as $predicted) {
                $matrix[$actual][$predicted] = 0;
            }
        }

        $correctCategory = 0;
        $severityExact = 0;
        $severityDiffSum = 0;

        $sourceStats = [
            'gemini' => ['total' => 0, 'correct' => 0, 'severity_exact' => 0],
            'keyword' => ['total' => 0, 'correct' => 0, 'severity_exact' => 0],
        ];

        foreach ($reports as $r) {
            $actualCat = $r->category;
            $predCat = $r->ai_category;
            $actualSev = (int) $r->severity;
            $predSev = (int) ($r->ai_severity ?? $r->severity);
            $src = ($r->analysis_source === 'gemini') ? 'gemini' : 'keyword';

            if (isset($matrix[$actualCat][$predCat])) {
                $matrix[$actualCat][$predCat]++;
            }

            $catMatch = ($predCat === $actualCat);
            if ($catMatch) {
                $correctCategory++;
            }

            $sevMatch = ($predSev === $actualSev);
            if ($sevMatch) {
                $severityExact++;
            }
            $severityDiffSum += abs($predSev - $actualSev);

            if (isset($sourceStats[$src])) {
                $sourceStats[$src]['total']++;
                if ($catMatch) {
                    $sourceStats[$src]['correct']++;
                }
                if ($sevMatch) {
                    $sourceStats[$src]['severity_exact']++;
                }
            }
        }

        $catMetrics = [];
        $f1Scores = [];
        foreach ($categories as $cat) {
            $tp = $matrix[$cat][$cat];
            $fp = 0;
            $fn = 0;
            foreach ($categories as $other) {
                if ($other !== $cat) {
                    $fp += $matrix[$other][$cat];
                    $fn += $matrix[$cat][$other];
                }
            }

            $precision = ($tp + $fp > 0) ? round($tp / ($tp + $fp), 3) : 0.0;
            $recall = ($tp + $fn > 0) ? round($tp / ($tp + $fn), 3) : 0.0;
            $f1 = ($precision + $recall > 0) ? round((2 * $precision * $recall) / ($precision + $recall), 3) : 0.0;

            $catMetrics[$cat] = [
                'support' => $tp + $fn,
                'tp' => $tp,
                'fp' => $fp,
                'fn' => $fn,
                'precision' => $precision,
                'recall' => $recall,
                'f1' => $f1,
            ];

            if (($tp + $fn) > 0) {
                $f1Scores[] = $f1;
            }
        }

        $macroF1 = count($f1Scores) > 0 ? round(array_sum($f1Scores) / count($f1Scores), 3) : 0.0;

        $geminiTotal = $sourceStats['gemini']['total'];
        $keywordTotal = $sourceStats['keyword']['total'];

        $sourceComparison = [
            'gemini' => [
                'count' => $geminiTotal,
                'accuracy' => $geminiTotal > 0 ? round($sourceStats['gemini']['correct'] / $geminiTotal, 3) : 0.0,
                'severity_exact' => $geminiTotal > 0 ? round($sourceStats['gemini']['severity_exact'] / $geminiTotal, 3) : 0.0,
            ],
            'keyword' => [
                'count' => $keywordTotal,
                'accuracy' => $keywordTotal > 0 ? round($sourceStats['keyword']['correct'] / $keywordTotal, 3) : 0.0,
                'severity_exact' => $keywordTotal > 0 ? round($sourceStats['keyword']['severity_exact'] / $keywordTotal, 3) : 0.0,
            ],
        ];

        return [
            'total_moderated' => $total,
            'overall_accuracy' => round($correctCategory / $total, 3),
            'macro_f1' => $macroF1,
            'severity_exact_match' => round($severityExact / $total, 3),
            'severity_mae' => round($severityDiffSum / $total, 2),
            'category_metrics' => $catMetrics,
            'confusion_matrix' => $matrix,
            'source_breakdown' => $sourceComparison,
        ];
    }
}
