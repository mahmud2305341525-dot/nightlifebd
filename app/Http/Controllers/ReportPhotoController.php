<?php

namespace App\Http\Controllers;

use App\Models\ReportPhoto;
use App\Services\IncidentPhotoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportPhotoController extends Controller
{
    /**
     * Serve a private incident photo or thumbnail with strict authorization and security headers.
     *
     * @param  string  $variant  'thumb' | 'full'
     */
    public function show(Request $request, ReportPhoto $photo, string $variant = 'thumb'): BinaryFileResponse
    {
        $report = $photo->report;
        if (! $report) {
            abort(404, __('Report not found.'));
        }

        $user = $request->user();
        $isAuthorized = false;

        // 1. Verified reports are part of public community safety records and verified venue reviews
        if ($report->status === 'verified') {
            $isAuthorized = true;
        }
        // 2. Administrators and moderators can inspect photos for any report (pending, verified, rejected)
        elseif ($user && $user->isStaff()) {
            $isAuthorized = true;
        }
        // 3. The report author can view their own attached photos at any status
        elseif ($user && $user->id === $report->user_id) {
            $isAuthorized = true;
        }

        if (! $isAuthorized) {
            abort(403, __('You are not authorized to view this photo.'));
        }

        $relPath = ($variant === 'full') ? $photo->path : $photo->thumbnail;

        // Locate file on private report_photos disk, with fallback checks for local/public
        $disk = Storage::disk(IncidentPhotoService::DISK);
        if (! $disk->exists($relPath)) {
            if (Storage::disk('local')->exists($relPath)) {
                $disk = Storage::disk('local');
            } elseif (Storage::disk('public')->exists($relPath)) {
                $disk = Storage::disk('public');
            } else {
                abort(404, __('Photo file not found on disk.'));
            }
        }

        $fullSystemPath = $disk->path($relPath);
        $mime = $disk->mimeType($relPath) ?: 'image/jpeg';
        $ext = pathinfo($fullSystemPath, PATHINFO_EXTENSION) ?: 'jpg';
        $isPublic = ($report->status === 'verified');

        $headers = [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="photo_'.$photo->id.'.'.$ext.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Pragma' => 'no-cache',
            'X-Frame-Options' => 'SAMEORIGIN',
        ];

        $response = response()->file($fullSystemPath, $headers);

        if ($isPublic) {
            $response->setPublic();
            $response->setMaxAge(86400);
        } else {
            $response->setPrivate();
            $response->headers->addCacheControlDirective('no-cache', true);
            $response->headers->addCacheControlDirective('no-store', true);
            $response->headers->addCacheControlDirective('must-revalidate', true);
        }

        return $response;
    }
}
