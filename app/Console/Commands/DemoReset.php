<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\DarkSpot;
use App\Models\ReportPhoto;
use App\Models\SafetyTimer;
use App\Models\Trip;
use App\Models\TripPing;
use App\Models\User;
use App\Models\VenueFavorite;
use App\Services\IncidentPhotoService;
use App\Services\SafetyScoreService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Resets all ephemeral demo state so repeated demonstrations start cleanly.
 *
 * Resets:
 *  - Clears active trips, trip pings, safety timers, audit logs, dark spots
 *  - Resets all user trust scores to the seeded default (0.5)
 *  - Recalculates all venue safety scores from the seeded reports
 *
 * Does NOT drop or re-seed: users, venues, reports, contacts.
 * Run before each demo to avoid "that report was already verified" accidents.
 */
class DemoReset extends Command
{
    protected $signature = 'demo:reset {--yes : Skip confirmation prompt}';

    protected $description = 'Reset ephemeral demo data (trips, timers, audit logs, trust scores) without re-seeding';

    public function __construct(private SafetyScoreService $scores)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! $this->option('yes') && ! $this->confirm('This will clear trips, timers, audit logs and reset trust scores. Continue?', true)) {
            $this->line('Aborted.');

            return self::SUCCESS;
        }

        $this->info('🔄 Resetting demo state…');

        // Clear ephemeral tables
        $this->task('Clear trip pings', fn () => TripPing::query()->delete());
        $this->task('Clear trips', fn () => Trip::query()->delete());
        $this->task('Clear safety timers', fn () => SafetyTimer::query()->delete());
        $this->task('Clear audit log', fn () => AuditLog::query()->delete());
        $this->task('Clear dark spots', fn () => DarkSpot::query()->delete());
        $this->task('Clear favorites', fn () => VenueFavorite::query()->delete());
        $this->task('Clear report photos', function () {
            ReportPhoto::query()->delete();
            Storage::disk(IncidentPhotoService::DISK)->deleteDirectory('photos');
            Storage::disk(IncidentPhotoService::DISK)->deleteDirectory('thumbs');
            Storage::disk('public')->deleteDirectory('reports');
        });

        // Reset trust scores to neutral baseline
        $this->task('Reset user trust scores', function () {
            User::query()->update(['trust_score' => 0.5]);
        });

        // Recalculate safety scores from the seeded verified reports
        $this->task('Recalculate safety scores', function () {
            $n = $this->scores->recalcAll();
            $this->line("  → Recalculated scores for {$n} venue(s).");
        });

        $this->newLine();
        $this->info('✅ Demo reset complete. Test accounts: user@test.com, safety@test.com, partner@test.com, admin@test.com (password: password)');

        return self::SUCCESS;
    }

    /** Helper for consistent output (mimics Artisan::task() which requires Laravel 10+). */
    private function task(string $label, callable $fn): void
    {
        $this->line("  → {$label}…");
        $fn();
    }
}
