<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyzeReportJob;
use App\Models\AuditLog;
use App\Models\Report;
use App\Models\ReportPhoto;
use App\Models\Venue;
use App\Services\IncidentPhotoService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function create(Request $request)
    {
        return view('reports.create', [
            'venues' => Venue::approved()->orderBy('name')->get(['id', 'name', 'area', 'lat', 'lng']),
            'selected' => $request->integer('venue') ?: null,
        ]);
    }

    public function store(Request $request, IncidentPhotoService $photoService)
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(['incident', 'review'])],
            'body' => ['required', 'string', 'min:10', 'max:500'],
            'venue_id' => ['nullable', Rule::exists('venues', 'id')->where('status', 'approved')],
            'lat' => ['nullable', 'numeric', 'between:20,27'],
            'lng' => ['nullable', 'numeric', 'between:88,93'],
            'incident_time' => ['nullable', 'date', 'before_or_equal:now'],
            'is_anonymous' => ['nullable', 'boolean'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'photos' => ['nullable', 'array', 'max:3'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'], // 5 MB each
        ], [
            'body.max' => 'Reports are limited to 500 characters.',
            'body.min' => 'Please describe what happened in at least 10 characters.',
            'photos.max' => 'You may upload up to 3 photos.',
            'photos.*.max' => 'Each photo must be under 5 MB.',
        ]);

        if (empty($data['venue_id']) && (empty($data['lat']) || empty($data['lng']))) {
            return back()->withInput()->withErrors(['venue_id' => 'Choose a venue or click the map to mark the place.']);
        }

        // Anti-abuse: Per-user, per-venue cooldown (prevent targeted score manipulation of a single venue)
        if (! empty($data['venue_id'])) {
            $recentVenueReport = Report::where('user_id', $request->user()->id)
                ->where('venue_id', $data['venue_id'])
                ->where('created_at', '>=', now()->subHours(24))
                ->exists();

            if ($recentVenueReport) {
                return back()->withInput()->withErrors([
                    'venue_id' => __('You have already submitted a report for this venue within the last 24 hours. Multiple submissions are limited to prevent score manipulation.'),
                ]);
            }
        }

        // Anti-abuse: Suspicious reporting pattern detection (velocity burst: >= 3 submissions in 10 minutes)
        $recentBurstCount = Report::where('user_id', $request->user()->id)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->count();
        $isBurstSubmission = ($recentBurstCount >= 3);

        // Deep binary & dimension validation before touching the database
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $file) {
                $photoService->validateFile($file);
            }
        }

        // Rating is only allowed for reviews
        $rating = ($data['kind'] === 'review' && ! empty($data['rating'])) ? $data['rating'] : null;

        $storedFiles = [];
        $disk = Storage::disk(IncidentPhotoService::DISK);

        try {
            $report = DB::transaction(function () use ($request, $data, $rating, $isBurstSubmission, $photoService, &$storedFiles) {
                $rep = Report::create([
                    'user_id' => $request->user()->id,
                    'venue_id' => $data['venue_id'] ?? null,
                    'kind' => $data['kind'],
                    'body' => strip_tags($data['body']),
                    'lat' => $data['lat'] ?? null,
                    'lng' => $data['lng'] ?? null,
                    'incident_time' => ! empty($data['incident_time']) ? Carbon::parse($data['incident_time']) : now(),
                    'is_anonymous' => $request->boolean('is_anonymous'),
                    'rating' => $rating,
                    'status' => 'pending',
                    'needs_review' => $isBurstSubmission,
                ]);

                if ($request->hasFile('photos')) {
                    foreach ($request->file('photos') as $file) {
                        $processed = $photoService->processAndStore($file, $rep);
                        $storedFiles[] = $processed['path'];
                        $storedFiles[] = $processed['thumbnail'];

                        ReportPhoto::create([
                            'report_id' => $rep->id,
                            'path' => $processed['path'],
                            'thumbnail' => $processed['thumbnail'],
                            'size_bytes' => $processed['size_bytes'],
                            'created_at' => now(),
                        ]);
                    }
                }

                AuditLog::record($request->user()->id, 'report.submitted', [
                    'report_id' => $rep->id,
                    'velocity_flag' => $isBurstSubmission,
                ]);

                return $rep;
            });
        } catch (\Throwable $e) {
            // Clean up any files that were stored before database transaction failure
            foreach ($storedFiles as $p) {
                if ($disk->exists($p)) {
                    $disk->delete($p);
                }
            }
            throw $e;
        }

        AnalyzeReportJob::send($report->id);

        return redirect()->route('reports.mine')->with('status', __('Thank you. Your report was submitted and is being analysed by AI before moderator review.'));
    }

    public function mine(Request $request)
    {
        return view('reports.mine', [
            'reports' => $request->user()->reports()->with(['venue:id,name', 'photos'])->latest()->paginate(15),
        ]);
    }

    public function edit(Request $request, Report $report)
    {
        abort_unless($report->user_id === $request->user()->id, 403, 'You can only edit your own reports.');
        abort_unless($report->status === 'pending', 403, 'Only pending reports can be edited. This report has already been reviewed.');

        return view('reports.edit', [
            'report' => $report,
        ]);
    }

    public function update(Request $request, Report $report)
    {
        abort_unless($report->user_id === $request->user()->id, 403, 'You can only edit your own reports.');
        abort_unless($report->status === 'pending', 403, 'Only pending reports can be edited. This report has already been reviewed.');

        $data = $request->validate([
            'body' => ['required', 'string', 'min:10', 'max:500'],
            'incident_time' => ['nullable', 'date', 'before_or_equal:now'],
            'is_anonymous' => ['nullable', 'boolean'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
        ], [
            'body.max' => 'Reports are limited to 500 characters.',
            'body.min' => 'Please describe what happened in at least 10 characters.',
        ]);

        $rating = ($report->kind === 'review' && ! empty($data['rating'])) ? (int) $data['rating'] : null;

        $report->update([
            'body' => strip_tags($data['body']),
            'incident_time' => ! empty($data['incident_time']) ? Carbon::parse($data['incident_time']) : $report->incident_time,
            'is_anonymous' => $request->boolean('is_anonymous'),
            'rating' => $rating,
            // Reset AI predictions for re-classification
            'category' => null,
            'severity' => null,
            'ai_category' => null,
            'ai_severity' => null,
            'summary_en' => null,
            'analysis_source' => null,
        ]);

        // Re-dispatch automated AI analyzer
        AnalyzeReportJob::send($report->id);

        AuditLog::record($request->user()->id, 'report.edited', [
            'report_id' => $report->id,
        ]);

        return redirect()->route('reports.mine')->with('status', __('Your pending report has been updated and re-submitted for AI classification.'));
    }

    public function destroy(Request $request, Report $report)
    {
        abort_unless($report->user_id === $request->user()->id, 403, 'You can only withdraw your own reports.');
        abort_unless($report->status === 'pending', 403, 'Only pending reports can be withdrawn. Verified or rejected reports cannot be deleted.');

        $report->delete();

        AuditLog::record($request->user()->id, 'report.withdrawn', [
            'report_id' => $report->id,
        ]);

        return redirect()->route('reports.mine')->with('status', __('Your pending report has been successfully withdrawn.'));
    }
}
