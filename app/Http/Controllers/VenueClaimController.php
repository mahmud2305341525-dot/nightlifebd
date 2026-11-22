<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use App\Models\VenueClaim;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VenueClaimController extends Controller
{
    public function store(Request $request, Venue $venue): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:190'],
            'note' => ['nullable', 'string', 'max:1000'],
            'evidence' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
        ]);
        abort_if(! $request->user(), 403);

        $existing = VenueClaim::where('venue_id', $venue->id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'pending')
            ->exists();
        abort_if($existing, 422, __('A pending claim already exists for this venue.'));

        $path = $request->file('evidence')->store('venue_claims', 'local');
        VenueClaim::create([
            'venue_id' => $venue->id,
            'user_id' => $request->user()->id,
            'evidence_path' => $path,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'note' => $data['note'] ?? null,
            'status' => 'pending',
        ]);

        return back()->with('status', __('Venue claim submitted for moderator review.'));
    }
}
