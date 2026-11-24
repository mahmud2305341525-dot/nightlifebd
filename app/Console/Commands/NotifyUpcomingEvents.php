<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\EventNotification;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NotifyUpcomingEvents extends Command
{
    protected $signature = 'events:notify-upcoming';

    protected $description = 'Dispatches notifications to users for upcoming concerts and night events starting soon';

    public function handle(): int
    {
        $upcomingEvents = Event::published()
            ->with('venue:id,name')
            ->whereBetween('starts_at', [now(), now()->addHours(24)])
            ->get();

        if ($upcomingEvents->isEmpty()) {
            $this->info('No events starting within the next 24 hours.');

            return self::SUCCESS;
        }

        $sentCount = 0;
        $hasErrors = false;

        foreach ($upcomingEvents as $event) {
            $title = '🎸 '.__('Concert Alert: :title', ['title' => $event->title]);
            $message = __('Happening tonight at :loc in :area. Check safety scores and book passes.', [
                'loc' => $event->venue ? $event->venue->name : $event->address,
                'area' => $event->area,
            ]);

            User::where('is_banned', false)->chunkById(250, function ($users) use ($event, $title, $message, &$sentCount, &$hasErrors) {
                $alreadyNotifiedUserIds = EventNotification::where('event_id', $event->id)
                    ->whereIn('user_id', $users->pluck('id'))
                    ->pluck('user_id')
                    ->flip();

                $now = now();
                $toInsert = [];

                foreach ($users as $user) {
                    if (! $alreadyNotifiedUserIds->has($user->id)) {
                        $toInsert[] = [
                            'user_id' => $user->id,
                            'event_id' => $event->id,
                            'title' => $title,
                            'message' => $message,
                            'is_read' => false,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                if (! empty($toInsert)) {
                    try {
                        $inserted = EventNotification::insertOrIgnore($toInsert);
                        $sentCount += $inserted;

                        $skipped = count($toInsert) - $inserted;
                        if ($skipped > 0) {
                            Log::info('Upcoming event notification deduplication', [
                                'event_id' => $event->id,
                                'attempted' => count($toInsert),
                                'inserted' => $inserted,
                                'skipped_duplicate' => $skipped,
                            ]);
                        }
                    } catch (\Throwable $e) {
                        $hasErrors = true;
                        Log::error('Upcoming event notification dispatch failed for chunk', [
                            'event_id' => $event->id,
                            'chunk_size' => count($toInsert),
                            'error' => $e->getMessage(),
                        ]);

                        $this->error("Failed to insert chunk for event {$event->id}: {$e->getMessage()}");
                    }
                }
            });
        }

        $this->info("Dispatched {$sentCount} event notifications across {$upcomingEvents->count()} upcoming events.");

        if ($hasErrors) {
            $this->error('Completed with failures. One or more notification chunks could not be dispatched.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
