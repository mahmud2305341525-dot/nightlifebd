<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Report;
use App\Models\ReportResponse;
use App\Models\Venue;
use Illuminate\Http\Request;

class ReportResponseController extends Controller
{
    public function index(Request $request)
    {
        $venueIds = $request->user()->hasRole('super_admin')
            ? Venue::pluck('id')
            : Venue::where('owner_id', $request->user()->id)->pluck('id');

        $reports = Report::whereIn('venue_id', $venueIds)
            ->with(['venue', 'responses.user'])
            ->latest()
            ->paginate(15);

        return view('partner.responses.index', compact('reports'));
    }

    public function store(Request $request, Report $report)
    {
        $venue = $report->venue;
        abort_unless($venue, 404, 'Report is not attached to any venue.');

        // Authorization: Must own the venue or be super_admin
        if (! $request->user()->hasRole('super_admin') && $venue->owner_id !== $request->user()->id) {
            abort(403, 'You are not authorized to respond on behalf of this venue.');
        }

        $data = $request->validate([
            'response' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'response.min' => 'Your response must be at least 10 characters long.',
            'response.max' => 'Your response may not exceed 1000 characters.',
        ]);

        $resp = ReportResponse::create([
            'report_id' => $report->id,
            'venue_id' => $venue->id,
            'user_id' => $request->user()->id,
            'response' => strip_tags($data['response']),
            'status' => 'pending',
        ]);

        AuditLog::record($request->user()->id, 'report_response.submitted', [
            'report_response_id' => $resp->id,
            'report_id' => $report->id,
            'venue_id' => $venue->id,
        ]);

        return back()->with('status', __('Your response has been submitted and is pending moderator review.'));
    }
}
