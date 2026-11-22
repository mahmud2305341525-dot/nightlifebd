<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\GeminiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class NightlifePreflight extends Command
{
    protected $signature = 'nightlife:preflight';

    protected $description = 'Fail when unsafe or unavailable production dependencies are configured';

    public function handle(GeminiService $gemini): int
    {
        $failures = [];
        $warnings = [];
        $production = app()->environment('production');

        if (config('app.debug')) {
            $production ? $failures[] = 'APP_DEBUG=true' : $warnings[] = 'APP_DEBUG=true';
        }
        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            $production ? $failures[] = 'MAIL_MAILER is '.config('mail.default') : $warnings[] = 'MAIL_MAILER is '.config('mail.default');
        }
        if (config('nightlife.require_real_mail') && in_array(config('mail.default'), ['log', 'array'], true)) {
            $production ? $failures[] = 'real mail is required but MAIL_MAILER is local-only' : $warnings[] = 'real mail is required';
        }
        if (config('nightlife.require_sms') && ! config('services.sms.driver')) {
            $production ? $failures[] = 'SMS is required but no SMS driver is configured' : $warnings[] = 'SMS is required';
        }
        if (! config('nightlife.demo_enabled')) {
            // Safe.
        } else {
            $production ? $failures[] = 'NIGHTLIFE_DEMO_ENABLED=true' : $warnings[] = 'NIGHTLIFE_DEMO_ENABLED=true';
        }

        $defaultDemoEmails = [
            'user@test.com',
            'partner@test.com',
            'safety@test.com',
            'admin@test.com',
        ];
        if (User::whereIn('email', $defaultDemoEmails)->exists()) {
            $production ? $failures[] = 'default demo accounts exist' : $warnings[] = 'default demo accounts exist';
        }

        if (config('nightlife.payments_driver', 'mock') !== 'none') {
            $production ? $failures[] = 'mock or enabled mock-style payments are active' : $warnings[] = 'payments driver is '.config('nightlife.payments_driver');
        }

        if (config('nightlife.routing_driver', 'ors') === 'osrm' || (! filled(config('services.ors.key')) && config('nightlife.routing_driver') === 'ors')) {
            $production ? $failures[] = 'public OSRM routing or an unconfigured ORS routing provider is in use' : $warnings[] = 'routing provider is not production-ready';
        }

        if (! config('nightlife.use_queue')) {
            $production ? $failures[] = 'NIGHTLIFE_USE_QUEUE=false' : $warnings[] = 'NIGHTLIFE_USE_QUEUE=false';
        }
        if (config('queue.default') === 'sync') {
            $production ? $failures[] = 'QUEUE_CONNECTION=sync' : $warnings[] = 'QUEUE_CONNECTION=sync';
        }

        $heartbeat = Cache::get('nightlife:scheduler-heartbeat');
        if (! $heartbeat) {
            $production ? $failures[] = 'scheduler heartbeat is missing' : $warnings[] = 'scheduler heartbeat is missing';
        }

        if (! $gemini->enabled()) {
            $production ? $failures[] = 'Gemini API key is not configured' : $warnings[] = 'Gemini is not configured; local fallback will be used';
        } else {
            try {
                $response = Http::timeout(15)
                    ->withHeaders(['x-goog-api-key' => config('services.gemini.key')])
                    ->post('https://generativelanguage.googleapis.com/v1beta/models/'.config('services.gemini.model').':generateContent', [
                        'contents' => [['parts' => [['text' => 'Reply with the single word OK.']]]],
                        'generationConfig' => ['maxOutputTokens' => 4, 'temperature' => 0],
                    ]);
                if (! $response->successful()) {
                    $production ? $failures[] = 'Gemini live test failed with HTTP '.$response->status() : $warnings[] = 'Gemini live test failed with HTTP '.$response->status();
                }
            } catch (\Throwable $e) {
                $production ? $failures[] = 'Gemini live test timed out or could not connect' : $warnings[] = 'Gemini live test failed';
            }
        }

        foreach ($warnings as $warning) {
            $this->warn($warning);
        }
        foreach ($failures as $failure) {
            $this->error($failure);
        }

        if ($failures) {
            $this->error('NightLifeBD preflight failed.');

            return self::FAILURE;
        }

        $this->info('NightLifeBD preflight passed.');

        return self::SUCCESS;
    }
}
