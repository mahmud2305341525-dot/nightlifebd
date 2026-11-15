<?php

namespace App\Models;

use App\Support\TimeBucket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Report extends Model
{
    protected $guarded = [];

    public const CATEGORIES = ['harassment', 'theft', 'violence', 'dark_road', 'overcharging', 'positive', 'other'];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'incident_time' => 'datetime',
            'verified_at' => 'datetime',
            'is_anonymous' => 'boolean',
            'needs_review' => 'boolean',
            'spam_probability' => 'float',
            'embedding' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function original(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'duplicate_of');
    }

    public function duplicates(): HasMany
    {
        return $this->hasMany(Report::class, 'duplicate_of');
    }

    public function scopeVerified(Builder $q): Builder
    {
        return $q->where('status', 'verified');
    }

    public function bucket(): string
    {
        return TimeBucket::fromHour((int) $this->incident_time->format('G'));
    }

    /** [lat, lng] from the report itself, otherwise from its venue. */
    public function coords(): ?array
    {
        if ($this->lat !== null && $this->lng !== null) {
            return [$this->lat, $this->lng];
        }
        if ($this->venue) {
            return [$this->venue->lat, $this->venue->lng];
        }

        return null;
    }

    public static function categoryLabel(?string $c): string
    {
        return match ($c) {
            'harassment' => __('Harassment'),
            'theft' => __('Theft / snatching'),
            'violence' => __('Violence'),
            'dark_road' => __('Dark / unsafe road'),
            'overcharging' => __('Overcharging'),
            'positive' => __('Positive experience'),
            'other' => __('Other'),
            default => __('Pending analysis'),
        };
    }
}
