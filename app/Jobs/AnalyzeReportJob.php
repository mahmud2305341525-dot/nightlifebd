<?php

namespace App\Jobs;

use App\Models\Report;
use App\Services\ReportAnalyzer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AnalyzeReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $reportId)
    {
    }

    public function handle(ReportAnalyzer $analyzer): void
    {
        $report = Report::find($this->reportId);
        if ($report) {
            $analyzer->analyze($report);
        }
    }

    public function failed(\Throwable $e): void
    {
        Report::whereKey($this->reportId)->update(['needs_review' => true, 'analysis_source' => 'failed']);
    }

    /** Run now-after-response (default) or via the database queue, depending on config. */
    public static function send(int $reportId): void
    {
        if (config('nightlife.use_queue')) {
            static::dispatch($reportId);
        } else {
            static::dispatchAfterResponse($reportId);
        }
    }
}
