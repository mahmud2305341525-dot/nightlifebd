<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ReportFlag;
use App\Services\SafetyScoreService;
use Illuminate\Http\Request;

class ReportFlagModerationController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status', 'pending');
        $flags = ReportFlag::with(['report.user', 'report.venue', 'user', 'reviewer'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(20);

        return view('admin.flags.index', compact('flags', 'status'));
    }

    public function dismiss(Request $request, ReportFlag $flag)
    {
        $flag->update([
            'status' => 'dismissed',
            'reviewed_by' => $request->user()->id,
            'action_taken' => 'dismissed',
        ]);

        AuditLog::record($request->user()->id, 'report_flag.dismissed', [
            'flag_id' => $flag->id,
            'report_id' => $flag->report_id,
        ]);

        return back()->with('status', __('Report flag was dismissed.'));
    }

    public function resolve(Request $request, ReportFlag $flag, SafetyScoreService $scores)
    {
        $action = $request->input('action', 'reject_report');
        $report = $flag->report;

        if ($action === 'ban_author' && $report->user) {
            $report->user->update(['is_banned' => true]);
            $report->update(['status' => 'rejected']);
            $scores->recalcAffectedBy($report);
            $actionText = 'Author banned and report rejected';
        } else {
            $report->update(['status' => 'rejected']);
            $scores->recalcAffectedBy($report);
            $actionText = 'Report rejected and removed from safety scoring';
        }

        $flag->update([
            'status' => 'resolved',
            'reviewed_by' => $request->user()->id,
            'action_taken' => $actionText,
        ]);

        // Resolve other pending flags on this same report
        ReportFlag::where('report_id', $report->id)
            ->where('status', 'pending')
            ->update([
                'status' => 'resolved',
                'reviewed_by' => $request->user()->id,
                'action_taken' => $actionText,
            ]);

        AuditLog::record($request->user()->id, 'report_flag.resolved', [
            'flag_id' => $flag->id,
            'report_id' => $report->id,
            'action' => $action,
        ]);

        return back()->with('status', __('Report flag resolved: :action.', ['action' => $actionText]));
    }
}
