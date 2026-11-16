<?php

namespace App\Models;

use App\Services\IncidentPhotoService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ReportPhoto extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::deleting(function (ReportPhoto $photo) {
            $disk = Storage::disk(IncidentPhotoService::DISK);
            if ($photo->path && $disk->exists($photo->path)) {
                $disk->delete($photo->path);
            }
            if ($photo->thumbnail && $disk->exists($photo->thumbnail)) {
                $disk->delete($photo->thumbnail);
            }

            // Also clean legacy public/local files if present
            if ($photo->path && Storage::disk('public')->exists($photo->path)) {
                Storage::disk('public')->delete($photo->path);
            }
            if ($photo->thumbnail && Storage::disk('public')->exists($photo->thumbnail)) {
                Storage::disk('public')->delete($photo->thumbnail);
            }
        });
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /** Secure authorized route for the full image. */
    public function url(): string
    {
        return route('reports.photos.show', ['photo' => $this, 'variant' => 'full']);
    }

    /** Secure authorized route for the thumbnail. */
    public function thumbUrl(): string
    {
        return route('reports.photos.show', ['photo' => $this, 'variant' => 'thumb']);
    }
}
