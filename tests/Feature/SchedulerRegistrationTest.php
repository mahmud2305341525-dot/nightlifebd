<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class SchedulerRegistrationTest extends TestCase
{
    public function test_safety_check_timers_command_is_registered_every_minute(): void
    {
        $schedule = app(Schedule::class);
        $events = collect($schedule->events());

        $timerEvent = $events->first(function ($event) {
            return str_contains($event->command, 'safety:check-timers');
        });

        $this->assertNotNull($timerEvent, 'safety:check-timers must be registered in the console schedule.');
        $this->assertSame('* * * * *', $timerEvent->expression, 'safety:check-timers must run every minute (* * * * *).');
    }

    public function test_trips_purge_command_is_registered_hourly(): void
    {
        $schedule = app(Schedule::class);
        $events = collect($schedule->events());

        $purgeEvent = $events->first(function ($event) {
            return str_contains($event->command, 'trips:purge');
        });

        $this->assertNotNull($purgeEvent, 'trips:purge must be registered in the console schedule.');
        $this->assertSame('0 * * * *', $purgeEvent->expression, 'trips:purge must run hourly (0 * * * *).');
    }

    public function test_safety_recalculate_command_is_registered_daily(): void
    {
        $schedule = app(Schedule::class);
        $events = collect($schedule->events());

        $recalcEvent = $events->first(function ($event) {
            return str_contains($event->command, 'safety:recalculate');
        });

        $this->assertNotNull($recalcEvent, 'safety:recalculate must be registered in the console schedule.');
        $this->assertSame('0 0 * * *', $recalcEvent->expression, 'safety:recalculate must run daily (0 0 * * *).');
    }
}
