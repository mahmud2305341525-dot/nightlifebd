<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public static function record(?int $userId, string $action, array $meta = []): self
    {
        return static::create(['user_id' => $userId, 'action' => $action, 'meta' => $meta]);
    }
}
