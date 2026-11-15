<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\Venue;
use App\Support\TimeBucket;
use Illuminate\Http\Request;

class VenueController extends Controller
{
    public function index(Request $request)
    {
        $adult = (bool) $request->user()?->is_adult_confirmed;
        $bucket = TimeBucket::normalize($request->input('when'));

        $venues = Venue::approved()
            ->with('scores')
            ->when(! $adult, fn ($q) => $q->where('is_restricted', false))
            ->filter($request->only(['q', 'category', 'area', 'price']))
            ->orderBy('name')->get();

        if ($badge = $request->input('badge')) {
            $venues = $venues->filter(fn ($v) => $v->badgeFor($bucket) === $badge)->values();
        }

        return view('venues.index', [
            'venues' => $venues,
            'areas' => Venue::approved()->distinct()->orderBy('area')->pluck('area'),
            'categories' => Venue::CATEGORIES,
            'adult' => $adult,
            'bucket' => $bucket,
        ]);
    }

    public function show(Request $request, Venue $venue)
    {
        abort_unless($venue->status === 'approved', 404);
        abort_if($venue->is_restricted && ! $request->user()?->is_adult_confirmed, 403,
            'You must confirm you are 18 or older to view this category.');

        $venue->load('scores');
        $reports = Report::verified()->where('venue_id', $venue->id)->whereNull('duplicate_of')
            ->withCount('duplicates')->with('user:id,name')->latest('incident_time')->limit(8)->get();

        return view('venues.show', [
            'venue' => $venue,
            'reports' => $reports,
            'bucket' => TimeBucket::current(),
        ]);
    }

    public function confirmAge(Request $request)
    {
        $request->user()->update(['is_adult_confirmed' => true]);

        return back();
    }
}
