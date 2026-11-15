<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Venue;
use App\Services\SafetyScoreService;
use Illuminate\Http\Request;

class VenueModerationController extends Controller
{
    public function index()
    {
        return view('admin.venues', ['venues' => Venue::with('owner:id,name')->where('status', 'pending')->latest()->get()]);
    }

    public function approve(Request $request, Venue $venue, SafetyScoreService $score)
    {
        $venue->update(['status' => 'approved']);
        $score->recalcVenue($venue);
        AuditLog::record($request->user()->id, 'venue.approved', ['venue_id' => $venue->id]);

        return back()->with('status', __('Venue approved.'));
    }

    public function reject(Request $request, Venue $venue)
    {
        $venue->update(['status' => 'rejected']);
        AuditLog::record($request->user()->id, 'venue.rejected', ['venue_id' => $venue->id]);

        return back()->with('status', __('Venue rejected.'));
    }
}
