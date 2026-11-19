<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name', 'email', 'password',
        'phone', 'gender', 'city', 'is_adult_confirmed', 'trust_score',
        'telegram_chat_id', 'locale', 'is_banned',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_adult_confirmed' => 'boolean',
            'is_banned' => 'boolean',
            'trust_score' => 'float',
        ];
    }

    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(EmergencyContact::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function venues(): HasMany
    {
        return $this->hasMany(Venue::class, 'owner_id');
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    public function safetyTimers(): HasMany
    {
        return $this->hasMany(SafetyTimer::class);
    }

    public function eventBookings(): HasMany
    {
        return $this->hasMany(EventBooking::class);
    }

    public function eventNotifications(): HasMany
    {
        return $this->hasMany(EventNotification::class);
    }

    public function favoriteVenues(): BelongsToMany
    {
        return $this->belongsToMany(Venue::class, 'venue_favorites')->withPivot('created_at');
    }

    public function hasFavorited(int $venueId): bool
    {
        return $this->favoriteVenues()->where('venue_id', $venueId)->exists();
    }

    public function isStaff(): bool
    {
        return $this->hasAnyRole(['safety_admin', 'super_admin']);
    }

    /** Reporter trust = (verified + 1) / (verified + rejected + 2). A new user starts at 0.5. Bounded between 0.10 and 0.90. */
    public function recalcTrust(): void
    {
        $verified = $this->reports()->where('status', 'verified')->count();
        $rejected = $this->reports()->where('status', 'rejected')->count();
        $rawTrust = ($verified + 1) / ($verified + $rejected + 2);
        // Cap trust at 0.90 max and 0.10 min so no single actor gains disproportionate scoring hegemony
        $cappedTrust = round(min(0.90, max(0.10, $rawTrust)), 2);
        $this->forceFill(['trust_score' => $cappedTrust])->save();
    }
}
