<?php

namespace App\Console\Commands;

use App\Services\SafetyScoreService;
use Illuminate\Console\Command;

class RecalculateSafety extends Command
{
    protected $signature = 'safety:recalculate';

    protected $description = 'Recalculate the time-aware safety score of every approved venue';

    public function handle(SafetyScoreService $svc): int
    {
        $this->info('Recalculated '.$svc->recalcAll().' venues.');

        return self::SUCCESS;
    }
}
