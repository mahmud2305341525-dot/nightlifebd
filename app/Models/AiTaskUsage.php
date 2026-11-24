<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiTaskUsage extends Model
{
    protected $guarded = [];

    public function scopeRecent($query, int $minutes = 1440)
    {
        return $query->where('created_at', '>=', now()->subMinutes($minutes));
    }
}
