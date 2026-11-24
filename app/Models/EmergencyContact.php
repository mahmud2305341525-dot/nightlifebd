<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmergencyContact extends Model
{
    protected $fillable = [
        'user_id', 'name', 'phone', 'relation', 'email', 'telegram_chat_id',
        'verification_status', 'telegram_verification_token', 'verified_at', 'last_reachable_at',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'last_reachable_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
