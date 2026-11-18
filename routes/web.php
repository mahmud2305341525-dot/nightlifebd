<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\DarkSpotController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataPrivacyController;
use App\Http\Controllers\DemoSimulatorController;
use App\Http\Controllers\EmergencyContactController;
use App\Http\Controllers\EventBookingController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventNotificationController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\HeatmapController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Partner;
use App\Http\Controllers\PlannerController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportFlagController;
use App\Http\Controllers\ReportPhotoController;
use App\Http\Controllers\RouteController;
use App\Http\Controllers\SafetyController;
use App\Http\Controllers\TimerController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\VenueController;
use App\Http\Middleware\EnsureDemoMode;
use Illuminate\Support\Facades\Route;

/* ---------- public ---------- */
Route::get('/health', HealthController::class)->name('health');
Route::view('/', 'welcome')->name('home');
Route::get('/lang/{locale}', LocaleController::class)->name('lang');
Route::view('/offline', 'offline')->name('offline');
Route::view('/how-it-works', 'how-it-works')->name('how-it-works');

Route::get('/venues', [VenueController::class, 'index'])->name('venues.index');
Route::get('/venues/{venue}', [VenueController::class, 'show'])->whereNumber('venue')->name('venues.show');

Route::get('/events', [EventController::class, 'index'])->name('events.index');
Route::get('/events/{event:slug}', [EventController::class, 'show'])->name('events.show');

Route::get('/route', [RouteController::class, 'index'])->name('route.index');
Route::post('/route/compute', [RouteController::class, 'compute'])->middleware('throttle:20,1')->name('route.compute');

Route::get('/heatmap', [HeatmapController::class, 'index'])->name('heatmap.index');
Route::get('/api/heat', [HeatmapController::class, 'data'])->middleware('throttle:60,1')->name('api.heat');
Route::get('/api/feed', [HeatmapController::class, 'feed'])->middleware('throttle:60,1')->name('api.feed');

Route::get('/near-me', [SafetyController::class, 'nearMe'])->name('near-me');
Route::get('/api/nearest', [SafetyController::class, 'nearest'])->middleware('throttle:60,1')->name('api.nearest');

Route::get('/planner', [PlannerController::class, 'index'])->name('planner.index');
Route::post('/planner', [PlannerController::class, 'generate'])->middleware('throttle:10,1')->name('planner.generate');

// secure incident photo delivery endpoint
Route::get('/reports/photos/{photo}/{variant?}', [ReportPhotoController::class, 'show'])
    ->where('variant', 'thumb|full')
    ->name('reports.photos.show');

// live trip link that a contact opens (secret 40-character token, expires automatically)
Route::get('/track/{trip:token}', [TripController::class, 'track'])->middleware('throttle:60,1')->name('track.show');
Route::get('/track/{trip:token}/data', [TripController::class, 'trackData'])->middleware('throttle:60,1')->name('track.data');

/* ---------- signed-in users ---------- */
Route::middleware(['auth', 'not_banned'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->middleware('verified')->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/profile/export', [DataPrivacyController::class, 'export'])->name('profile.export');

    Route::post('/age-confirm', [VenueController::class, 'confirmAge'])->name('age.confirm');
    Route::post('/venues/{venue}/favorite', [VenueController::class, 'toggleFavorite'])->whereNumber('venue')->name('venues.favorite');

    Route::get('/contacts', [EmergencyContactController::class, 'index'])->name('contacts.index');
    Route::post('/contacts', [EmergencyContactController::class, 'store'])->name('contacts.store');
    Route::delete('/contacts/{contact}', [EmergencyContactController::class, 'destroy'])->name('contacts.destroy');

    Route::get('/reports/create', [ReportController::class, 'create'])->name('reports.create');
    Route::post('/reports', [ReportController::class, 'store'])->middleware('throttle:10,1')->name('reports.store');
    Route::get('/my-reports', [ReportController::class, 'mine'])->name('reports.mine');
    Route::get('/reports/{report}/edit', [ReportController::class, 'edit'])->whereNumber('report')->name('reports.edit');
    Route::put('/reports/{report}', [ReportController::class, 'update'])->whereNumber('report')->name('reports.update');
    Route::delete('/reports/{report}', [ReportController::class, 'destroy'])->whereNumber('report')->name('reports.destroy');
    Route::post('/reports/{report}/flag', [ReportFlagController::class, 'store'])->whereNumber('report')->name('reports.flag');
    Route::post('/dark-spots', [DarkSpotController::class, 'store'])->middleware('throttle:10,1')->name('dark-spots.store');

    Route::get('/sos', [SafetyController::class, 'sosPage'])->name('sos.index');
    Route::post('/sos', [SafetyController::class, 'sos'])->middleware('throttle:6,1')->name('sos.trigger');
    Route::get('/api/emergency-data', [SafetyController::class, 'emergencyData'])->name('api.emergency');

    Route::get('/trips', [TripController::class, 'index'])->name('trips.index');
    Route::post('/trips', [TripController::class, 'store'])->middleware('throttle:10,1')->name('trips.store');
    Route::get('/trips/{trip:token}', [TripController::class, 'show'])->name('trips.show');
    Route::post('/trips/{trip:token}/ping', [TripController::class, 'ping'])->name('trips.ping');
    Route::post('/trips/{trip:token}/end', [TripController::class, 'end'])->name('trips.end');

    Route::get('/timer', [TimerController::class, 'index'])->name('timer.index');
    Route::post('/timer', [TimerController::class, 'store'])->middleware('throttle:10,1')->name('timer.store');
    Route::post('/timer/{timer}/confirm', [TimerController::class, 'confirm'])->name('timer.confirm');
    Route::delete('/timer/{timer}', [TimerController::class, 'destroy'])->name('timer.destroy');

    Route::view('/fake-call', 'fakecall')->name('fakecall');

    /* Events & Bookings */
    Route::post('/events/{event}/book', [EventBookingController::class, 'store'])->whereNumber('event')->name('events.book');
    Route::get('/events/passes/{reference}', [EventBookingController::class, 'show'])->name('events.bookings.show');
    Route::get('/my-bookings', [EventBookingController::class, 'mine'])->name('events.bookings.mine');
    Route::post('/events/bookings/{booking}/cancel', [EventBookingController::class, 'cancel'])->name('events.bookings.cancel');
    Route::get('/notifications/events', [EventNotificationController::class, 'index'])->name('events.notifications');
    Route::get('/api/notifications/unread-count', [EventNotificationController::class, 'unreadCount'])->name('api.notifications.unread');
});

/* ---------- venue partners ---------- */
Route::middleware(['auth', 'not_banned', 'role:venue_partner|super_admin'])->prefix('partner')->name('partner.')->group(function () {
    Route::get('/venues', [Partner\VenueController::class, 'index'])->name('venues.index');
    Route::get('/venues/create', [Partner\VenueController::class, 'create'])->name('venues.create');
    Route::post('/venues', [Partner\VenueController::class, 'store'])->name('venues.store');
    Route::get('/venues/{venue}/edit', [Partner\VenueController::class, 'edit'])->name('venues.edit');
    Route::put('/venues/{venue}', [Partner\VenueController::class, 'update'])->name('venues.update');
    Route::post('/venues/{venue}/coach', [Partner\VenueController::class, 'coach'])->name('venues.coach');

    Route::get('/responses', [Partner\ReportResponseController::class, 'index'])->name('responses.index');
    Route::post('/reports/{report}/responses', [Partner\ReportResponseController::class, 'store'])->whereNumber('report')->name('responses.store');
});

/* ---------- moderators ---------- */
Route::middleware(['auth', 'not_banned', 'role:safety_admin|super_admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');
    Route::get('/venues', [Admin\VenueModerationController::class, 'index'])->name('venues');
    Route::post('/venues/{venue}/approve', [Admin\VenueModerationController::class, 'approve'])->name('venues.approve');
    Route::post('/venues/{venue}/reject', [Admin\VenueModerationController::class, 'reject'])->name('venues.reject');

    Route::get('/reports', [Admin\ReportModerationController::class, 'index'])->name('reports');
    Route::post('/reports/{report}/verify', [Admin\ReportModerationController::class, 'verify'])->name('reports.verify');
    Route::post('/reports/{report}/reject', [Admin\ReportModerationController::class, 'reject'])->name('reports.reject');
    Route::post('/reports/{report}/analyze', [Admin\ReportModerationController::class, 'analyze'])->name('reports.analyze');

    Route::post('/responses/{response}/approve', [Admin\ReportResponseModerationController::class, 'approve'])->name('responses.approve');
    Route::post('/responses/{response}/reject', [Admin\ReportResponseModerationController::class, 'reject'])->name('responses.reject');

    Route::get('/flags', [Admin\ReportFlagModerationController::class, 'index'])->name('flags.index');
    Route::post('/flags/{flag}/dismiss', [Admin\ReportFlagModerationController::class, 'dismiss'])->name('flags.dismiss');
    Route::post('/flags/{flag}/resolve', [Admin\ReportFlagModerationController::class, 'resolve'])->name('flags.resolve');

    /* Event Moderation & Scanner */
    Route::get('/events', [Admin\EventModerationController::class, 'index'])->name('events.index');
    Route::get('/events/create', [Admin\EventModerationController::class, 'create'])->name('events.create');
    Route::post('/events', [Admin\EventModerationController::class, 'store'])->name('events.store');
    Route::get('/events/scanner', [Admin\EventModerationController::class, 'scanner'])->name('events.scanner');
    Route::post('/events/check-in', [Admin\EventModerationController::class, 'verifyCheckIn'])->name('events.checkin');
});
Route::middleware(['auth', 'not_banned', 'role:super_admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/users', [Admin\UserController::class, 'index'])->name('users');
    Route::patch('/users/{user}', [Admin\UserController::class, 'update'])->name('users.update');
});

/* ---------- demo simulator (explicit configuration only) ---------- */
Route::middleware([EnsureDemoMode::class, 'throttle:30,1'])->prefix('demo')->name('demo.')->group(function () {
    Route::get('/', [DemoSimulatorController::class, 'index'])->name('index');
    Route::post('/switch/{role}', [DemoSimulatorController::class, 'switchUser'])->name('switch');
    Route::post('/incident', [DemoSimulatorController::class, 'simulateIncident'])->name('incident');
    Route::post('/timer', [DemoSimulatorController::class, 'simulateTimerAlert'])->name('timer');
    Route::post('/spam', [DemoSimulatorController::class, 'simulateSpam'])->name('spam');
    Route::post('/reset', [DemoSimulatorController::class, 'reset'])->name('reset');
});

require __DIR__.'/auth.php';
