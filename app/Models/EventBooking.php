<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EventBooking extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'float',
            'total_price' => 'float',
            'checked_in_at' => 'datetime',
        ];
    }

    public static function boot(): void
    {
        parent::boot();

        static::creating(function (EventBooking $booking) {
            if (empty($booking->booking_reference)) {
                $booking->booking_reference = 'NL-EVT-'.strtoupper(Str::random(6));
            }
            if (empty($booking->qr_token)) {
                $booking->qr_token = hash('sha256', Str::random(40).microtime(true));
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function ticketTier(): BelongsTo
    {
        return $this->belongsTo(EventTicketTier::class, 'ticket_tier_id');
    }

    public function checkedInByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function isCheckedIn(): bool
    {
        return $this->status === 'checked_in';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function markCheckedIn(User $officer): void
    {
        $this->update([
            'status' => 'checked_in',
            'checked_in_at' => now(),
            'checked_in_by' => $officer->id,
        ]);
    }
}
