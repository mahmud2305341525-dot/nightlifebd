<?php

namespace App\Http\Controllers;

use App\Models\SafetyTimer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TimerController extends Controller
{
    public function index(Request $request)
    {
        return view('timer.index', [
            'timers' => $request->user()->safetyTimers()->latest()->limit(8)->get(),
            'hasContacts' => $request->user()->emergencyContacts()->exists(),
        ]);
    }

    public function store(Request $request)
    {
        $d = $request->validate([
            'due_at' => ['required', 'date', 'after:now'],
            'note' => ['nullable', 'string', 'max:120'],
        ]);

        $maxActive = (int) config('nightlife.max_active_timers', 1);

        return DB::transaction(function () use ($request, $d, $maxActive) {
            $activeRunning = $request->user()->safetyTimers()
                ->whereNull('confirmed_at')
                ->where('due_at', '>', now())
                ->lockForUpdate()
                ->count();

            if ($activeRunning >= $maxActive) {
                return back()->withInput()->withErrors([
                    'due_at' => __('You already have an active safety timer running. Please confirm or cancel it before starting a new one.'),
                ]);
            }

            $request->user()->safetyTimers()->create([
                'due_at' => Carbon::parse($d['due_at']),
                'note' => $d['note'] ?? null,
            ]);

            return back()->with('status', __('Safety timer started. If you do not confirm in time, your contacts are alerted.'));
        });
    }

    public function confirm(Request $request, SafetyTimer $timer)
    {
        abort_unless($timer->user_id === $request->user()->id, 403);
        $timer->update(['confirmed_at' => now()]);

        return back()->with('status', __('Glad you are safe! Timer cancelled.'));
    }

    public function destroy(Request $request, SafetyTimer $timer)
    {
        abort_unless($timer->user_id === $request->user()->id, 403);
        $timer->delete();

        return back();
    }
}
