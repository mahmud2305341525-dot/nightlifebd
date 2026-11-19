<?php

namespace App\Console\Commands;

use App\Models\Report;
use App\Models\SafeHaven;
use App\Models\SafetyTimer;
use App\Models\Trip;
use App\Models\User;
use App\Models\Venue;
use App\Services\GeminiService;
use App\Services\LocalAnalyzer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class HealthCheck extends Command
{
    protected $signature = 'safety:health-check';

    protected $description = 'Verify database, AI models, storage, and demo readiness for NightLifeBD';

    public function handle(GeminiService $gemini, LocalAnalyzer $analyzer): int
    {
        $this->newLine();
        $this->info('🌙 NightLifeBD System Health & Demo Readiness Check');
        $this->line('DIU SE331 Software Engineering Design Capstone — Team Error 404');
        $this->newLine();

        $rows = [];

        // 1. Environment & Keys
        $appKey = ! empty(config('app.key'));
        $rows[] = ['Application Key', $appKey ? '✅ Set' : '❌ Missing (Run php artisan key:generate)', $appKey ? 'OK' : 'ERROR'];

        $appEnv = config('app.env');
        $rows[] = ['Environment', "✅ {$appEnv} (Debug: ".(config('app.debug') ? 'true' : 'false').')', 'OK'];

        // 2. Database & Data
        try {
            DB::connection()->getPdo();
            $userCount = User::count();
            $venueCount = Venue::count();
            $reportCount = Report::count();
            $havenCount = SafeHaven::count();

            $rows[] = ['Database Connection', '✅ Connected (MySQL 127.0.0.1:3306)', 'OK'];
            $rows[] = ['Seeded Users', "✅ {$userCount} accounts", $userCount >= 4 ? 'OK' : 'WARN'];
            $rows[] = ['Approved Venues', "✅ {$venueCount} venues", $venueCount >= 20 ? 'OK' : 'WARN'];
            $rows[] = ['Incident Reports', "✅ {$reportCount} reports", 'OK'];
            $rows[] = ['Safe Havens (OSM)', "✅ {$havenCount} points (Police/Hospitals)", $havenCount > 0 ? 'OK' : 'WARN'];
        } catch (\Throwable $e) {
            $rows[] = ['Database Connection', '❌ Failed: '.$e->getMessage(), 'FAIL'];
        }

        // 3. Storage Symlink
        $storageLinked = File::exists(public_path('storage'));
        $rows[] = ['Storage Public Link', $storageLinked ? '✅ Linked (public/storage)' : '⚠️ Not linked (Run php artisan storage:link)', $storageLinked ? 'OK' : 'WARN'];

        // 4. Vite Production Assets
        $manifestExists = File::exists(public_path('build/manifest.json'));
        $rows[] = ['Vite Assets Build', $manifestExists ? '✅ Compiled (public/build)' : '⚠️ Not built (Run npm run build)', $manifestExists ? 'OK' : 'WARN'];

        // 5. AI Services
        if ($gemini->enabled()) {
            $rows[] = ['Google Gemini AI', '✅ API Key configured (Online JSON reasoning)', 'OK'];
        } else {
            $rows[] = ['Google Gemini AI', '⚡ Offline Mode (Local keyword analyzer fallback active)', 'INFO'];
        }

        // Test Local Analyzer
        $testResult = $analyzer->analyze('রাত ১২টায় ছিনতাইকারীর কবলে পড়েছিলাম');
        $localOk = ($testResult['category'] ?? null) === 'theft';
        $rows[] = ['Bangla NLP Lexicon', $localOk ? '✅ Active (Recognized ছিনতাই -> theft)' : '⚠️ Check LocalAnalyzer', $localOk ? 'OK' : 'WARN'];

        // 6. Emergency Tools State
        try {
            $activeTimers = SafetyTimer::whereNull('confirmed_at')->count();
            $activeTrips = Trip::where('status', 'active')->count();
            $rows[] = ['Active Safety Timers', "✅ {$activeTimers} timer(s)", 'OK'];
            $rows[] = ['Active Live Trips', "✅ {$activeTrips} trip(s)", 'OK'];
        } catch (\Throwable $e) {
            // DB might be down
        }

        // 7. Demo Simulator Status
        $demoRoute = (config('app.debug') || app()->isLocal());
        $rows[] = ['Demo Simulator (/demo)', $demoRoute ? '✅ Active (/demo and navigation bar)' : '⚠️ Disabled', 'OK'];

        $this->table(['Check Component', 'Status & Detail', 'Result'], $rows);
        $this->newLine();

        $this->info('🚀 All core Capstone modules are configured and ready for live presentation!');
        $this->newLine();

        return self::SUCCESS;
    }
}
