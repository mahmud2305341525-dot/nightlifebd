<?php

namespace App\Models;

use App\Support\TimeBucket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venue extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'has_cctv' => 'boolean',
            'has_guard' => 'boolean',
            'is_restricted' => 'boolean',
            'coach_tips' => 'array',
            'coach_at' => 'datetime',
        ];
    }

    public const CATEGORIES = ['cafe', 'rooftop', 'restaurant', 'eatery', 'event', 'lounge'];

    protected static function booted(): void
    {
        static::saving(function (Venue $venue) {
            if ($venue->lighting !== null && ($venue->lighting < 1 || $venue->lighting > 5)) {
                throw new \InvalidArgumentException('Lighting must be between 1 and 5');
            }
            if ($venue->price_tier !== null && ($venue->price_tier < 1 || $venue->price_tier > 3)) {
                throw new \InvalidArgumentException('Price tier must be between 1 and 3');
            }
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function reportResponses(): HasMany
    {
        return $this->hasMany(ReportResponse::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(VenueScore::class);
    }

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'venue_favorites')->withPivot('created_at');
    }

    /** Verified reviews with a star rating for this venue. */
    public function reviews(): HasMany
    {
        return $this->hasMany(Report::class)->where('kind', 'review')->whereNotNull('rating')->where('status', 'verified');
    }

    /** Average star rating from verified reviews (null if none). */
    public function avgRating(): ?float
    {
        if (array_key_exists('reviews_avg_rating', $this->attributes)) {
            $avg = $this->attributes['reviews_avg_rating'];

            return $avg !== null ? round((float) $avg, 1) : null;
        }

        $avg = $this->reviews()->avg('rating');

        return $avg !== null ? round($avg, 1) : null;
    }

    /** Count of verified reviews. */
    public function reviewCount(): int
    {
        if (array_key_exists('reviews_count', $this->attributes)) {
            return (int) $this->attributes['reviews_count'];
        }

        return $this->reviews()->count();
    }

    public function scopeApproved(Builder $q): Builder
    {
        return $q->where('status', 'approved');
    }

    public function scopeFilter(Builder $q, array $f): Builder
    {
        return $q
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where('name', 'like', "%{$v}%"))
            ->when($f['category'] ?? null, fn ($q, $v) => $q->where('category', $v))
            ->when($f['area'] ?? null, fn ($q, $v) => $q->where('area', $v))
            ->when($f['price'] ?? null, fn ($q, $v) => $q->where('price_tier', $v));
    }

    /** Score row for a time bucket (relation should be eager loaded for lists). */
    public function scoreFor(string $bucket): ?VenueScore
    {
        return $this->scores->firstWhere('bucket', $bucket);
    }

    public function badgeFor(string $bucket): string
    {
        return $this->scoreFor($bucket)?->badge ?? 'unrated';
    }

    public function tagList(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->vibe_tags))));
    }

    /** Text used for similarity matching in the Night Planner. */
    public function searchText(): string
    {
        return implode(' ', [$this->name, $this->category, $this->area, $this->vibe_tags, $this->description]);
    }

    public static function currentBucket(): string
    {
        return TimeBucket::current();
    }
}
