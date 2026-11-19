<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportFlag extends Model
{
    use HasFactory;

    protected $guarded = [];

    public const REASONS = [
        'spam' => 'Spam / Advertising',
        'false_information' => 'False / Misleading Information',
        'harassment' => 'Harassment / Defamation',
        'offensive' => 'Offensive Language',
        'other' => 'Other Concern',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
