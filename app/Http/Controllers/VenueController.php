<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\Venue;
use App\Models\VenueFavorite;
use App\Support\TimeBucket;
use Illuminate\Http\Request;

class VenueController extends Controller
{
    public function index(Request $request)
    {
        $adult = (bool) $request->user()?->is_adult_confirmed;
        $bucket = TimeBucket::normalize($request->input('when'));

        $query = Venue::approved()
            ->with('scores')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->when(! $adult, fn ($q) => $q->where('is_restricted', false))
            ->filter($request->only(['q', 'category', 'area', 'price']))
            ->orderBy('name');

        // Badge filter is computed (not a raw column), so we must load and filter in PHP.
        // For all other cases, paginate at the DB level.
        if ($badge = $request->input('badge')) {
            $venues = $query->get()->filter(fn ($v) => $v->badgeFor($bucket) === $badge)->values();
        } else {
            $venues = $query->paginate(12)->withQueryString();
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

        $venue->load('scores')->loadAvg('reviews', 'rating')->loadCount('reviews');
        $reports = Report::verified()->where('venue_id', $venue->id)->whereNull('duplicate_of')
            ->withCount('duplicates')->with(['user:id,name', 'photos', 'approvedResponses.user'])->latest('incident_time')->limit(8)->get();

        // Reviews data
        $reviews = Report::verified()
            ->where('venue_id', $venue->id)
            ->where('kind', 'review')
            ->whereNotNull('rating')
            ->with(['user:id,name', 'photos'])
            ->latest('created_at')
            ->limit(10)
            ->get();
        $avgRating = $venue->avgRating();
        $reviewCount = $venue->reviewCount();

        // Favorite status
        $isFavorited = $request->user() ? $request->user()->hasFavorited($venue->id) : false;

        return view('venues.show', [
            'venue' => $venue,
            'reports' => $reports,
            'reviews' => $reviews,
            'avgRating' => $avgRating,
            'reviewCount' => $reviewCount,
            'isFavorited' => $isFavorited,
            'bucket' => TimeBucket::current(),
        ]);
    }

    public function toggleFavorite(Request $request, Venue $venue)
    {
        $user = $request->user();
        $existing = VenueFavorite::where('user_id', $user->id)->where('venue_id', $venue->id)->first();

        if ($existing) {
            $existing->delete();
            $status = 'removed';
        } else {
            VenueFavorite::create(['user_id' => $user->id, 'venue_id' => $venue->id, 'created_at' => now()]);
            $status = 'added';
        }

        if ($request->wantsJson()) {
            return response()->json(['status' => $status]);
        }

        return back()->with('status', $status === 'added' ? __('Venue added to favorites!') : __('Venue removed from favorites.'));
    }

    public function confirmAge(Request $request)
    {
        $request->user()->update(['is_adult_confirmed' => true]);

        return back();
    }
}
