<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\EventNotification;
use App\Models\User;
use Illuminate\Console\Command;

class NotifyUpcomingEvents extends Command
{
    protected $signature = 'events:notify-upcoming';
    protected $description = 'Dispatches notifications to users for upcoming concerts and night events starting soon';

    public function handle(): int
    {
        $upcomingEvents = Event::published()
            ->whereBetween('starts_at', [now(), now()->addHours(24)])
            ->get();

        if ($upcomingEvents->isEmpty()) {
            $this->info('No events starting within the next 24 hours.');
            return self::SUCCESS;
        }

        $sentCount = 0;

        foreach ($upcomingEvents as $event) {
            // Find active non-banned users
            $users = User::where('is_banned', false)->get();

            foreach ($users as $user) {
                $alreadyNotified = EventNotification::where('user_id', $user->id)
                    ->where('event_id', $event->id)
                    ->exists();

                if (! $alreadyNotified) {
                    EventNotification::create([
                        'user_id' => $user->id,
                        'event_id' => $event->id,
                        'title' => "🎸 " . __('Concert Alert: :title', ['title' => $event->title]),
                        'message' => __('Happening tonight at :loc in :area. Check safety scores and book passes.', [
                            'loc' => $event->venue ? $event->venue->name : $event->address,
                            'area' => $event->area,
                        ]),
                        'is_read' => false,
                    ]);
                    $sentCount++;
                }
            }
        }

        $this->info("Dispatched {$sentCount} event notifications across {$upcomingEvents->count()} upcoming events.");

        return self::SUCCESS;
    }
}
