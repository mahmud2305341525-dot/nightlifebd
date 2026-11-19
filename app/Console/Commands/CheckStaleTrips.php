<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Trip;
use App\Services\AlertService;
use Illuminate\Console\Command;

class CheckStaleTrips extends Command
{
    protected $signature = 'trips:check-stale';

    protected $description = 'Alert emergency contacts when an active live trip has had no location updates for 15+ minutes or has stalled past its expected duration';

    public function handle(AlertService $alerts): int
    {
        $cutoff = now()->subMinutes(15);

        $staleTrips = Trip::with('user.emergencyContacts')
            ->whereNull('ended_at')
            ->whereNull('stale_alerted_at')
            ->where(function ($q) use ($cutoff) {
                $q->where(function ($sub) use ($cutoff) {
                    $sub->whereNotNull('last_ping_at')->where('last_ping_at', '<=', $cutoff);
                })->orWhere(function ($sub) use ($cutoff) {
                    $sub->whereNull('last_ping_at')->where('created_at', '<=', $cutoff);
                })->orWhere('expires_at', '<=', now());
            })
            ->get();

        $count = 0;
        foreach ($staleTrips as $trip) {
            $user = $trip->user;
            if (! $user || $user->emergencyContacts->isEmpty()) {
                continue;
            }

            $trackLink = route('track.show', $trip->token);
            $lastCoords = ($trip->last_lat && $trip->last_lng)
                ? "near {$trip->last_lat}, {$trip->last_lng}"
                : 'initial departure point';

            $msg = "NightLifeBD Safety Alert: {$user->name}'s active live trip"
                .($trip->destination ? " to {$trip->destination}" : '')
                ." has stopped sending location updates for over 15 minutes (last seen {$lastCoords})."
                ." View live tracking: {$trackLink}."
                .' Please call them to ensure their safety. In an emergency dial 999.';

            $sent = $alerts->notifyContacts($user, 'NightLifeBD live trip safety alert', $msg);

            $trip->update(['stale_alerted_at' => now()]);

            AuditLog::record($user->id, 'trip.stale_alerted', [
                'trip_id' => $trip->id,
                'contacts' => $sent['contacts'],
                'email' => $sent['email'],
                'telegram' => $sent['telegram'],
                'is_simulation' => $sent['is_simulation'],
            ]);

            $this->line("Sent stale trip safety alert to contacts of {$user->name} (#{$user->id})");
            $count++;
        }

        $this->info("{$count} stale live trip(s) alerted.");

        return self::SUCCESS;
    }
}
