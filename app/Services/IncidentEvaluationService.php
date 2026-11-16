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
}
