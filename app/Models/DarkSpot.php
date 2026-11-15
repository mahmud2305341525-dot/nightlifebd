<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DarkSpot extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['lat' => 'float', 'lng' => 'float'];
    }
}
