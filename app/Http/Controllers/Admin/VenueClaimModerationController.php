<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\VenueClaim;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VenueClaimModerationController extends Controller
{
    public function index(): View
    {
        return view('admin.venue-claims', [
            'claims' => VenueClaim::with(['venue', 'user'])->where('status', 'pending')->latest()->paginate(20),
        ]);
    }

    public function approve(Request $request, VenueClaim $claim): RedirectResponse
    {
        $claim->update(['status' => 'approved', 'reviewed_at' => now()]);
        $claim->venue->update([
            'owner_id' => $claim->user_id,
            'verification_level' => 'owner_verified',
        ]);
        $claim->venue->claims()->where('status', 'pending')->update(['status' => 'withdrawn', 'reviewed_at' => now()]);
        AuditLog::record($request->user()->id, 'venue_claim.approved', [
            'claim_id' => $claim->id,
            'venue_id' => $claim->venue_id,
        ]);

        return back()->with('status', __('Venue claim approved.'));
    }

    public function reject(Request $request, VenueClaim $claim): RedirectResponse
    {
        $claim->update(['status' => 'rejected', 'reviewed_at' => now()]);
        AuditLog::record($request->user()->id, 'venue_claim.rejected', [
            'claim_id' => $claim->id,
            'venue_id' => $claim->venue_id,
        ]);

        return back()->with('status', __('Venue claim rejected.'));
    }
}
