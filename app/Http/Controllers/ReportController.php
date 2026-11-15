<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyzeReportJob;
use App\Models\AuditLog;
use App\Models\Report;
use App\Models\Venue;
use Illuminate\Http\Request;
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

    public function store(Request $request)
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(['incident', 'review'])],
            'body' => ['required', 'string', 'min:10', 'max:500'],
            'venue_id' => ['nullable', Rule::exists('venues', 'id')->where('status', 'approved')],
            'lat' => ['nullable', 'numeric', 'between:20,27'],
            'lng' => ['nullable', 'numeric', 'between:88,93'],
            'incident_time' => ['nullable', 'date', 'before_or_equal:now'],
            'is_anonymous' => ['nullable', 'boolean'],
        ], [
            'body.max' => 'Reports are limited to 500 characters.',
            'body.min' => 'Please describe what happened in at least 10 characters.',
        ]);

        if (empty($data['venue_id']) && (empty($data['lat']) || empty($data['lng']))) {
            return back()->withInput()->withErrors(['venue_id' => 'Choose a venue or click the map to mark the place.']);
        }

        $report = Report::create([
            'user_id' => $request->user()->id,
            'venue_id' => $data['venue_id'] ?? null,
            'kind' => $data['kind'],
            'body' => strip_tags($data['body']),
            'lat' => $data['lat'] ?? null,
            'lng' => $data['lng'] ?? null,
            'incident_time' => ! empty($data['incident_time']) ? \Carbon\Carbon::parse($data['incident_time']) : now(),
            'is_anonymous' => $request->boolean('is_anonymous'),
            'status' => 'pending',
        ]);
        AuditLog::record($request->user()->id, 'report.submitted', ['report_id' => $report->id]);

        AnalyzeReportJob::send($report->id);

        return redirect()->route('reports.mine')->with('status', __('Thank you. Your report was submitted and is being analysed by AI before moderator review.'));
    }

    public function mine(Request $request)
    {
        return view('reports.mine', [
            'reports' => $request->user()->reports()->with('venue:id,name')->latest()->paginate(15),
        ]);
    }
}
