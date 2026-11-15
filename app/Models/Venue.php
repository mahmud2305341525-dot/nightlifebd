<?php

namespace App\Models;

use App\Support\TimeBucket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(VenueScore::class);
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
