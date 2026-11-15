<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripPing extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['lat' => 'float', 'lng' => 'float', 'created_at' => 'datetime'];
    }
}
