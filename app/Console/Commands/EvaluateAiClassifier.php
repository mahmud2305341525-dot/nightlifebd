<?php

namespace App\Console\Commands;

use App\Services\IncidentEvaluationService;
use Illuminate\Console\Command;

class EvaluateAiClassifier extends Command
{
    protected $signature = 'ai:evaluate {--dataset= : Path to alternative JSON dataset}';

    protected $description = 'Evaluate the incident classification pipeline against the benchmark dataset';

    public function handle(IncidentEvaluationService $evaluator): int
    {
        $dataset = $this->option('dataset');
        $this->info('Starting Incident Classification Benchmark Evaluation...');

        $metrics = $evaluator->evaluateDataset($dataset);

        $this->line('');
        $this->info("Dataset Version: {$metrics['dataset_version']} | Total Samples: {$metrics['total_samples']}");
        $this->line('Overall Accuracy:     '.($metrics['overall_accuracy'] * 100).'%');
        $this->line("Macro F1-Score:       {$metrics['macro_f1']}");
        $this->line('Severity Exact Match: '.($metrics['severity_exact_match'] * 100).'%');
        $this->line("Severity MAE:         {$metrics['severity_mae']}");
        $this->line('Language Accuracy:    '.($metrics['language_accuracy'] * 100).'%');
        $this->line('Spam Detection Rate:  '.($metrics['spam_tpr'] * 100).'%');
        $this->line('Spam False Alarm:     '.($metrics['spam_fpr'] * 100).'%');
        $this->line('');

        $headers = ['Category', 'Support', 'Precision', 'Recall', 'F1-Score'];
        $rows = [];
        foreach ($metrics['category_metrics'] as $cat => $m) {
            $rows[] = [$cat, $m['support'], $m['precision'], $m['recall'], $m['f1']];
        }
        $this->table($headers, $rows);

        if (! empty($metrics['failures'])) {
            $this->warn('Sample Mismatches ('.count($metrics['failures']).'):');
            foreach (array_slice($metrics['failures'], 0, 5) as $f) {
                $this->line(" - [{$f['id']}] Exp: {$f['expected_category']}, Pred: {$f['predicted_category']} | \"{$f['text']}\"");
            }
        } else {
            $this->info('All benchmark samples matched target categories!');
        }

        return self::SUCCESS;
    }
}
