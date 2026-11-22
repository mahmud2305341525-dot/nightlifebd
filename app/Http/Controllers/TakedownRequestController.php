<?php

namespace App\Http\Controllers;

use App\Models\TakedownRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TakedownRequestController extends Controller
{
    public function create(): View
    {
        return view('legal.takedown');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'requester_email' => ['required', 'email', 'max:190'],
            'reason' => ['required', 'string', 'min:20', 'max:2000'],
            'report_id' => ['nullable', 'integer', 'exists:reports,id'],
            'venue_id' => ['nullable', 'integer', 'exists:venues,id'],
        ]);

        TakedownRequest::create([
            'report_id' => $data['report_id'] ?? null,
            'venue_id' => $data['venue_id'] ?? null,
            'user_id' => $request->user()?->id,
            'requester_email' => strtolower($data['requester_email']),
            'reason' => strip_tags($data['reason']),
            'status' => 'pending',
        ]);

        return back()->with('status', __('Takedown/defamation complaint submitted for review.'));
    }
}
