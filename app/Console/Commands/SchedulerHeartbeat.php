<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SchedulerHeartbeat extends Command
{
    protected $signature = 'nightlife:scheduler-heartbeat';

    protected $description = 'Write the Laravel scheduler heartbeat timestamp';

    public function handle(): int
    {
        Cache::put('nightlife:scheduler-heartbeat', now()->toIso8601String(), now()->addMinutes(10));
        $this->info('Scheduler heartbeat recorded.');

        return self::SUCCESS;
    }
}
