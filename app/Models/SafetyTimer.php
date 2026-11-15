<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SafetyTimer extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'confirmed_at' => 'datetime', 'alerted_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function status(): string
    {
        if ($this->confirmed_at) {
            return 'confirmed';
        }
        if ($this->alerted_at) {
            return 'alerted';
        }

        return $this->due_at->isPast() ? 'overdue' : 'running';
    }
}
