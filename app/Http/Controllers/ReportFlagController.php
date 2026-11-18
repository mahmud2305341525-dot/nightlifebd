<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Report;
use App\Models\ReportFlag;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportFlagController extends Controller
{
    public function store(Request $request, Report $report)
    {
        // Prevent duplicate flagging by same user
        $existing = ReportFlag::where('report_id', $report->id)
            ->where('user_id', $request->user()->id)
            ->exists();

        if ($existing) {
            return back()->with('error', __('You have already submitted a flag for this report. Our moderation team is reviewing it.'));
        }

        $data = $request->validate([
            'reason' => ['required', Rule::in(array_keys(ReportFlag::REASONS))],
            'details' => ['nullable', 'string', 'max:500'],
        ]);

        $flag = ReportFlag::create([
            'report_id' => $report->id,
            'user_id' => $request->user()->id,
            'reason' => $data['reason'],
            'details' => ! empty($data['details']) ? strip_tags($data['details']) : null,
            'status' => 'pending',
        ]);

        // If a report receives 3 or more community flags, automatically elevate to needs_review
        $pendingFlagsCount = ReportFlag::where('report_id', $report->id)->where('status', 'pending')->count();
        if ($pendingFlagsCount >= 3) {
            $report->update(['needs_review' => true]);
        }

        AuditLog::record($request->user()->id, 'report.flagged', [
            'flag_id' => $flag->id,
            'report_id' => $report->id,
            'reason' => $data['reason'],
        ]);

        return back()->with('status', __('Thank you. This report has been flagged and submitted to our moderation queue.'));
    }
}
