<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventTicketTier extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'capacity' => 'integer',
            'booked_count' => 'integer',
            'max_per_user' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(EventBooking::class, 'ticket_tier_id');
    }

    public function isAvailable(): bool
    {
        return $this->booked_count < $this->capacity;
    }

    public function remainingCount(): int
    {
        return max(0, $this->capacity - $this->booked_count);
    }
}
