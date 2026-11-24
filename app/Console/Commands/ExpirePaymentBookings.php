<?php

namespace App\Console\Commands;

use App\Models\EventBooking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpirePaymentBookings extends Command
{
    protected $signature = 'payments:expire';

    protected $description = 'Cancel pending payment bookings and release held seats';

    public function handle(): int
    {
        $count = 0;
        EventBooking::where('status', 'pending_payment')
            ->whereNotNull('payment_expires_at')
            ->where('payment_expires_at', '<=', now())
            ->with('ticketTier')
            ->chunkById(100, function ($bookings) use (&$count) {
                foreach ($bookings as $booking) {
                    DB::transaction(function () use ($booking) {
                        $booking->update(['status' => 'cancelled', 'payment_status' => 'expired']);
                        $tier = $booking->ticketTier;
                        if ($tier) {
                            $decrement = min($tier->booked_count, $booking->quantity);
                            if ($decrement > 0) {
                                $tier->decrement('booked_count', $decrement);
                            }
                        }
                    });
                }
                $count += $bookings->count();
            });

        $this->info("{$count} payment booking(s) expired.");

        return self::SUCCESS;
    }
}
