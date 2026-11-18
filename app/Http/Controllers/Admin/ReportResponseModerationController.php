<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ReportResponse;
use Illuminate\Http\Request;

class ReportResponseModerationController extends Controller
{
    public function approve(Request $request, ReportResponse $response)
    {
        $response->update([
            'status' => 'approved',
            'moderated_by' => $request->user()->id,
            'moderated_at' => now(),
            'moderation_note' => $request->input('note'),
        ]);

        AuditLog::record($request->user()->id, 'report_response.approved', [
            'report_response_id' => $response->id,
            'report_id' => $response->report_id,
            'venue_id' => $response->venue_id,
        ]);

        return back()->with('status', __('Venue response approved and published.'));
    }

    public function reject(Request $request, ReportResponse $response)
    {
        $response->update([
            'status' => 'rejected',
            'moderated_by' => $request->user()->id,
            'moderated_at' => now(),
            'moderation_note' => $request->input('note', 'Rejected by moderator'),
        ]);

        AuditLog::record($request->user()->id, 'report_response.rejected', [
            'report_response_id' => $response->id,
            'report_id' => $response->report_id,
            'venue_id' => $response->venue_id,
        ]);

        return back()->with('status', __('Venue response rejected.'));
    }
}
