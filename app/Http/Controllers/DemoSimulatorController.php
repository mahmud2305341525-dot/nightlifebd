<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\DarkSpot;
use App\Models\Report;
use App\Models\ReportPhoto;
use App\Models\SafetyTimer;
use App\Models\Trip;
use App\Models\TripPing;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueFavorite;
use App\Services\AlertService;
use App\Services\IncidentPhotoService;
use App\Services\SafetyScoreService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Demo Simulator & Scenario Switcher.
 * Exclusively active when explicitly enabled in configuration for presentations.
 */
class DemoSimulatorController extends Controller
{
    public function __construct()
    {
        abort_unless(config('nightlife.demo_enabled', false), 404);
    }

    /** Dedicated presentation dashboard view */
    public function index()
    {
        return view('demo.index', [
            'users' => [
                'user' => User::where('email', 'user@test.com')->first(),
                'safety' => User::where('email', 'safety@test.com')->first(),
                'partner' => User::where('email', 'partner@test.com')->first(),
                'admin' => User::where('email', 'admin@test.com')->first(),
            ],
            'venueCount' => Venue::approved()->count(),
            'reportCount' => Report::verified()->count(),
            'timerCount' => SafetyTimer::whereNull('confirmed_at')->count(),
        ]);
    }

    /** 1-Click user login / role switch for authenticated demo operators */
    public function switchUser(Request $request, string $role)
    {
        if (! Auth::check()) {
            return redirect()->guest(route('login'));
        }

        abort_unless(Auth::user()->isStaff(), 403, __('Unauthorized. Staff privileges required to switch demo roles.'));

        $roleMap = [
            'user' => 'user@test.com',
            'partner' => 'partner@test.com',
            'safety' => 'safety@test.com',
            'admin' => 'admin@test.com',
        ];

        $email = $roleMap[$role] ?? null;
        abort_unless($email, 404, 'Unknown demo role');

        $user = User::where('email', $email)->first();
        if (! $user) {
            return back()->with('error', "User {$email} not found. Did you run php artisan migrate --seed?");
        }

        $prevUser = Auth::user();
        Auth::login($user);
        AuditLog::record($user->id, 'demo.role_switched', [
            'from_user' => $prevUser?->email,
            'to_user' => $user->email,
            'role' => $role,
        ]);

        return back()->with('status', "Switched to {$user->name} ({$role})");
    }

    /** Scenario 1: Inject realistic verified incident and recalculate affected venue/area */
    public function simulateIncident(Request $request, SafetyScoreService $scores)
    {
        if (! Auth::check()) {
            return redirect()->guest(route('login'));
        }
        $user = Auth::user();

        // Target a Dhanmondi venue or first approved venue
        $venue = Venue::approved()->where('area', 'like', '%Dhanmondi%')->first() ?? Venue::approved()->first();

        $lat = $venue ? $venue->lat + 0.0004 : 23.7465;
        $lng = $venue ? $venue->lng + 0.0003 : 90.3760;

        $report = Report::create([
            'user_id' => $user->id,
            'venue_id' => $venue?->id,
            'kind' => 'incident',
            'category' => 'theft',
            'severity' => 4,
            'language' => 'bn',
            'body' => 'রাত ১২:৩০ এ ধানমন্ডি লেকের পাশের রাস্তায় ছিনতাইকারীর কবলে পড়েছিলাম। আলো খুব কম ছিল। সবাই সাবধানে থাকবেন।',
            'summary_en' => 'Snatching attempt at night near the lake road. Poor street lighting.',
            'lat' => $lat,
            'lng' => $lng,
            'incident_time' => now()->subMinutes(25),
            'status' => 'verified',
            'verified_at' => now(),
            'verified_by' => User::where('email', 'safety@test.com')->value('id') ?? $user->id,
            'is_anonymous' => false,
            'needs_review' => false,
            'spam_probability' => 0.04,
        ]);

        $scores->recalcAffectedBy($report);
        $user->recalcTrust();

        $venueName = $venue ? $venue->name : 'Dhanmondi 27';

        return back()->with('status', "🚨 Live Incident Injected! Verified Theft report added near {$venueName}. Safety scores updated.");
    }

    /** Scenario 2: Simulate expired safety timer triggering emergency alert */
    public function simulateTimerAlert(Request $request, AlertService $alerts)
    {
        if (! Auth::check()) {
            return redirect()->guest(route('login'));
        }
        $user = Auth::user();

        // Ensure user has at least one mock emergency contact for demonstration
        if (! $user->emergencyContacts()->exists()) {
            $user->emergencyContacts()->create([
                'name' => 'Emergency Family',
                'phone' => '01712345678',
                'relation' => 'Family',
            ]);
        }

        $due = now()->subMinutes(3);
        $timer = SafetyTimer::create([
            'user_id' => $user->id,
            'due_at' => $due,
            'note' => 'Walking from Dhanmondi 27 to home (Demo Simulation)',
        ]);

        // Dispatch alert
        $msg = "{$user->name} set a safety timer for {$due->format('h:i A')} and has NOT confirmed they are safe. Note: {$timer->note}. In an emergency dial 999.";
        $sent = $alerts->notifyContacts($user, 'NightLifeBD Emergency Safety Alert', $msg);
        $timer->update(['alerted_at' => now()]);
        AuditLog::record($user->id, 'timer.alert', ['timer_id' => $timer->id] + $sent);

        return back()->with('status', '⏱️ Safety Timer Expired! Emergency contacts alerted via AlertService (logged in storage/logs/laravel.log).');
    }

    /** Scenario 3: Simulate spam/malicious report rejection and watch trust score drop */
    public function simulateSpam(Request $request)
    {
        if (! Auth::check()) {
            return redirect()->guest(route('login'));
        }
        $user = Auth::user();

        $oldTrust = $user->trust_score;

        // Create spam report
        $report = Report::create([
            'user_id' => $user->id,
            'venue_id' => null,
            'kind' => 'incident',
            'category' => 'other',
            'severity' => 1,
            'language' => 'en',
            'body' => 'FREE BITCOIN PRIZE CLICK HERE http://spam.example.com instant cash win',
            'summary_en' => 'Promotional spam link.',
            'lat' => 23.78,
            'lng' => 90.41,
            'incident_time' => now(),
            'status' => 'rejected',
            'verified_at' => now(),
            'needs_review' => false,
            'spam_probability' => 0.98,
        ]);

        $user->recalcTrust();
        $user->refresh();

        return back()->with('status', "🛡️ Spammer Rejected! User trust score recalculated: {$oldTrust} → {$user->trust_score} (Laplace Smoothing formula applied).");
    }

    /** 1-Click Demo Reset (same as php artisan demo:reset --yes) */
    public function reset(Request $request, SafetyScoreService $scores)
    {
        if (! Auth::check()) {
            return redirect()->guest(route('login'));
        }

        abort_unless(Auth::user()->isStaff(), 403, __('Unauthorized. Staff privileges required to reset demo state.'));

        TripPing::query()->delete();
        Trip::query()->delete();
        SafetyTimer::query()->delete();
        AuditLog::query()->delete();
        DarkSpot::query()->delete();
        VenueFavorite::query()->delete();
        ReportPhoto::query()->delete();
        Storage::disk(IncidentPhotoService::DISK)->deleteDirectory('photos');
        Storage::disk(IncidentPhotoService::DISK)->deleteDirectory('thumbs');
        Storage::disk('public')->deleteDirectory('reports');

        User::query()->update(['trust_score' => 0.5]);
        $count = $scores->recalcAll();

        return back()->with('status', "🔄 Demo state cleanly reset! ({$count} venue scores recalculated, ephemeral trips/timers cleared).");
    }
}
