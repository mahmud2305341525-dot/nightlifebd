<?php

namespace App\Models;

use App\Support\TimeBucket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Report extends Model
{
    use SoftDeletes;

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
            'rating' => 'integer',
            'pii_scrubbed' => 'boolean',
            'defamation_flagged' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Report $report) {
            if ($report->severity !== null && ($report->severity < 1 || $report->severity > 5)) {
                throw new \InvalidArgumentException('Severity must be between 1 and 5');
            }
            if ($report->rating !== null && ($report->rating < 1 || $report->rating > 5)) {
                throw new \InvalidArgumentException('Rating must be between 1 and 5');
            }
        });

        static::deleting(function (Report $report) {
            foreach ($report->photos as $photo) {
                $photo->delete();
            }

            // Promote earliest duplicate so community corroborations survive deletion
            // without turning into multiple independent incidents
            $earliestDupe = $report->duplicates()->orderBy('id')->first();
            if ($earliestDupe) {
                $earliestDupe->update(['duplicate_of' => null]);
                $report->duplicates()->where('id', '!=', $earliestDupe->id)->update(['duplicate_of' => $earliestDupe->id]);
            }
        });
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

    public function photos(): HasMany
    {
        return $this->hasMany(ReportPhoto::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(ReportResponse::class);
    }

    public function approvedResponses(): HasMany
    {
        return $this->hasMany(ReportResponse::class)->where('status', 'approved');
    }

    public function flags(): HasMany
    {
        return $this->hasMany(ReportFlag::class);
    }

    /** Is this a venue review with a star rating? */
    public function isReview(): bool
    {
        return $this->kind === 'review' && $this->rating !== null;
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

    /**
     * Privacy Protection: Fuzz coordinates to ~110m (3 decimal places) for public display
     * on standalone reports older than 48h to prevent pinpointing exact home addresses of victims.
     */
    public function displayCoords(): ?array
    {
        $c = $this->coords();
        if (! $c) {
            return null;
        }

        if (! $this->venue_id && $this->incident_time && $this->incident_time->diffInHours(now()) > 48) {
            return [round($c[0], 3), round($c[1], 3)];
        }

        return [round($c[0], 5), round($c[1], 5)];
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
