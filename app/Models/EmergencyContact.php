<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmergencyContact extends Model
{
    protected $fillable = ['user_id', 'name', 'phone', 'relation', 'email', 'telegram_chat_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
