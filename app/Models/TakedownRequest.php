<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TakedownRequest extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }
}
