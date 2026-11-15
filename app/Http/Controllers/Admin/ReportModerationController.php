<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Report;
use App\Services\ReportAnalyzer;
use App\Services\SafetyScoreService;
use Illuminate\Http\Request;

class ReportModerationController extends Controller
{
    public function index(Request $request)
    {
        $status = in_array($request->input('status'), ['pending', 'verified', 'rejected'], true) ? $request->input('status') : 'pending';

        $reports = Report::with(['user:id,name,trust_score', 'venue:id,name', 'original:id,summary_en'])
            ->withCount('duplicates')
            ->where('status', $status)
            ->orderByRaw('severity IS NULL')       // un-analysed last
            ->orderByDesc('severity')
            ->orderByDesc('created_at')
            ->paginate(15)->withQueryString();

        return view('admin.reports', ['reports' => $reports, 'status' => $status]);
    }

    public function verify(Request $request, Report $report, SafetyScoreService $score)
    {
        return $this->decide($request, $report, 'verified', $score);
    }

    public function reject(Request $request, Report $report, SafetyScoreService $score)
    {
        return $this->decide($request, $report, 'rejected', $score);
    }

    public function analyze(Report $report, ReportAnalyzer $analyzer)
    {
        $analyzer->analyze($report);

        return back()->with('status', __('Report analysed.'));
    }

    private function decide(Request $request, Report $report, string $status, SafetyScoreService $score)
    {
        $report->update(['status' => $status, 'verified_by' => $request->user()->id, 'verified_at' => now(), 'needs_review' => false]);
        $report->user->recalcTrust();
        AuditLog::record($request->user()->id, "report.{$status}", ['report_id' => $report->id]);
        $score->recalcAffectedBy($report->load('venue'));

        return back()->with('status', $status === 'verified' ? __('Report verified. Safety scores updated.') : __('Report rejected.'));
    }
}
