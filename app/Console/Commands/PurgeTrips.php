<?php

namespace App\Console\Commands;

use App\Models\Trip;
use App\Models\TripPing;
use Illuminate\Console\Command;

class PurgeTrips extends Command
{
    protected $signature = 'trips:purge';

    protected $description = 'Delete location pings 72 hours after a trip ended or expired (privacy)';

    public function handle(): int
    {
        $cut = now()->subHours(72);
        $ids = Trip::where(fn ($q) => $q->where('ended_at', '<', $cut)->orWhere(fn ($q2) => $q2->whereNull('ended_at')->where('expires_at', '<', $cut)))->pluck('id');
        $pings = TripPing::whereIn('trip_id', $ids)->delete();
        Trip::whereIn('id', $ids)->update(['last_lat' => null, 'last_lng' => null]);
        $this->info("Purged {$pings} location pings from {$ids->count()} old trips.");

        return self::SUCCESS;
    }
}
