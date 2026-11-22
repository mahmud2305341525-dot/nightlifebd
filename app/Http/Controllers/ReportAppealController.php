<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\ReportAppeal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReportAppealController extends Controller
{
    public function store(Request $request, Report $report): RedirectResponse
    {
        abort_unless($report->user_id === $request->user()->id, 403);
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:20', 'max:2000'],
        ]);
        ReportAppeal::create([
            'report_id' => $report->id,
            'user_id' => $request->user()->id,
            'reason' => strip_tags($data['reason']),
            'status' => 'pending',
        ]);

        return back()->with('status', __('Appeal submitted for moderator review.'));
    }
}
