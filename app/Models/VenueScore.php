<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VenueScore extends Model
{
    protected $guarded = [];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }
}
