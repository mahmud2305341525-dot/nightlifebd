<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use App\Services\SafetyScoreService;
use App\Services\VenueCoachService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VenueController extends Controller
{
    public function index(Request $request)
    {
        $q = Venue::with('scores')->orderBy('name');
        if (! $request->user()->hasRole('super_admin')) {
            $q->where('owner_id', $request->user()->id);
        }

        return view('partner.venues.index', ['venues' => $q->get()]);
    }

    public function create()
    {
        return view('partner.venues.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['owner_id'] = $request->user()->id;
        $data['status'] = 'pending';
        Venue::create($data);

        return redirect()->route('partner.venues.index')->with('status', __('Venue submitted for admin approval.'));
    }

    public function edit(Request $request, Venue $venue)
    {
        $this->authorizeVenue($request, $venue);

        return view('partner.venues.edit', compact('venue'));
    }

    public function update(Request $request, Venue $venue, SafetyScoreService $score)
    {
        $this->authorizeVenue($request, $venue);
        $venue->update($this->validated($request));
        if ($venue->status === 'approved') {
            $score->recalcVenue($venue);
        }

        return redirect()->route('partner.venues.index')->with('status', __('Venue updated.'));
    }

    public function coach(Request $request, Venue $venue, VenueCoachService $coach)
    {
        $this->authorizeVenue($request, $venue);
        $coach->tips($venue, force: true);

        return back()->with('status', __('Coach tips refreshed.'));
    }

    private function authorizeVenue(Request $request, Venue $venue): void
    {
        abort_unless($venue->owner_id === $request->user()->id || $request->user()->hasRole('super_admin'), 403);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'category' => ['required', Rule::in(Venue::CATEGORIES)],
            'area' => ['required', 'string', 'max:60'],
            'price_tier' => ['required', 'integer', 'between:1,3'],
            'lat' => ['required', 'numeric', 'between:20,27'],
            'lng' => ['required', 'numeric', 'between:88,93'],
            'opens_at' => ['nullable', 'date_format:H:i'],
            'closes_at' => ['nullable', 'date_format:H:i'],
            'lighting' => ['required', 'integer', 'between:1,5'],
            'vibe_tags' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
        ], [
            'name.required' => 'Venue name is required.',
            'lat.required' => 'Click the map to choose the venue location.',
        ]);
        $data['has_cctv'] = $request->boolean('has_cctv');
        $data['has_guard'] = $request->boolean('has_guard');
        $data['is_restricted'] = $data['category'] === 'lounge';

        return $data;
    }
}
