<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'lat' => 'float',
            'lng' => 'float',
            'is_featured' => 'boolean',
        ];
    }

    public const CATEGORIES = [
        'concert' => 'Rock / Live Concert',
        'live_music' => 'Acoustic / Indie Gig',
        'festival' => 'Night Festival',
        'night_market' => 'Street Food & Night Market',
        'cultural' => 'Cultural & Art Show',
    ];

    public static function boot(): void
    {
        parent::boot();

        static::creating(function (Event $event) {
            if (empty($event->slug)) {
                $baseSlug = Str::slug($event->title);
                $slug = $baseSlug;
                $counter = 1;
                while (static::where('slug', $slug)->exists()) {
                    $slug = $baseSlug . '-' . $counter++;
                }
                $event->slug = $slug;
            }
        });
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function ticketTiers(): HasMany
    {
        return $this->hasMany(EventTicketTier::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(EventBooking::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(EventNotification::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('ends_at', '>=', now()->subHours(2));
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function isSoldOut(): bool
    {
        $tiers = $this->ticketTiers;
        if ($tiers->isEmpty()) {
            return false;
        }

        return $tiers->every(fn (EventTicketTier $tier) => $tier->booked_count >= $tier->capacity);
    }

    public function lowestPrice(): float
    {
        return (float) ($this->ticketTiers()->min('price') ?? 0.0);
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst($this->category);
    }

    public function getVenueSafetyScore(): ?float
    {
        if (! $this->venue) {
            return null;
        }

        $bucket = \App\Support\TimeBucket::current();
        $score = $this->venue->scoreFor($bucket);

        return $score ? (float) $score->score : null;
    }
}
