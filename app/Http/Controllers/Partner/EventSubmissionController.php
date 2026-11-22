<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Venue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class EventSubmissionController extends Controller
{
    public function create(): View
    {
        return view('partner.events.create', [
            'venues' => Venue::approved()->orderBy('name')->get(['id', 'name', 'area', 'lat', 'lng']),
            'categories' => Event::CATEGORIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'category' => ['required', 'in:'.implode(',', array_keys(Event::CATEGORIES))],
            'venue_id' => ['required', 'exists:venues,id'],
            'address' => ['required', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'description' => ['required', 'string', 'max:5000'],
        ]);
        $venue = Venue::findOrFail($data['venue_id']);

        Event::create([
            'title' => $data['title'],
            'slug' => Str::slug($data['title']).'-'.Str::random(6),
            'category' => $data['category'],
            'venue_id' => $venue->id,
            'organizer_id' => $request->user()->id,
            'area' => $venue->area,
            'address' => $data['address'],
            'lat' => $venue->lat,
            'lng' => $venue->lng,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'description' => $data['description'],
            'status' => 'draft',
            'submission_status' => 'pending',
        ]);

        return back()->with('status', __('Event submitted for administrator approval.'));
    }
}
