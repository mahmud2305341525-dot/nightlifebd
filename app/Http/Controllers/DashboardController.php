<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use App\Support\TimeBucket;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $bucket = TimeBucket::current();

        return view('dashboard', [
            'bucket' => $bucket,
            'reports' => $user->reports()->latest()->limit(5)->get(),
            'contactCount' => $user->emergencyContacts()->count(),
            'activeTrip' => $user->trips()->whereNull('ended_at')->where('expires_at', '>', now())->latest()->first(),
            'timer' => $user->safetyTimers()->whereNull('confirmed_at')->whereNull('alerted_at')->orderBy('due_at')->first(),
            'cautionVenues' => Venue::approved()->with('scores')->get()
                ->filter(fn ($v) => $v->badgeFor($bucket) === 'caution')->take(4),
        ]);
    }
}
