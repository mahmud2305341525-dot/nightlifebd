<?php

namespace App\Http\Controllers;

use App\Models\EventNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EventNotificationController extends Controller
{
    public function index(): View
    {
        $notifications = auth()->user()
            ->eventNotifications()
            ->with('event')
            ->latest()
            ->paginate(15);

        // Mark all as read when user visits the notification page
        auth()->user()->eventNotifications()->where('is_read', false)->update(['is_read' => true]);

        return view('events.notifications', [
            'notifications' => $notifications,
        ]);
    }

    public function markAsRead(EventNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === auth()->id(), 403);

        $notification->update(['is_read' => true]);

        return back();
    }

    public function unreadCount(): JsonResponse
    {
        $count = auth()->user()
            ? auth()->user()->eventNotifications()->where('is_read', false)->count()
            : 0;

        return response()->json(['unread' => $count]);
    }
}
