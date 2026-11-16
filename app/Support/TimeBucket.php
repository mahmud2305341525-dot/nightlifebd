<?php

namespace App\Support;

class TimeBucket
{
    public const ALL = ['day', 'evening', 'night', 'late'];

    public static function fromHour(int $hour): string
    {
        $h = (($hour % 24) + 24) % 24;

        return match (true) {
            $h >= 6 && $h < 18 => 'day',
            $h >= 18 && $h < 22 => 'evening',
            $h >= 22 || $h < 2 => 'night',
            default => 'late',
        };
    }

    public static function current(): string
    {
        return self::fromHour((int) now()->format('G'));
    }

    public static function normalize(?string $bucket): string
    {
        return in_array($bucket, self::ALL, true) ? $bucket : self::current();
    }

    /** A representative hour for each bucket (used by the heatmap slider and planner). */
    public static function hourFor(string $bucket): int
    {
        return ['day' => 12, 'evening' => 20, 'night' => 0, 'late' => 3][$bucket] ?? 20;
    }

    public static function label(string $bucket): string
    {
        return match ($bucket) {
            'day' => __('Daytime (6 AM - 6 PM)'),
            'evening' => __('Evening (6 PM - 10 PM)'),
            'night' => __('Night (10 PM - 2 AM)'),
            default => __('Late night (2 AM - 6 AM)'),
        };
    }

    public static function labels(): array
    {
        $out = [];
        foreach (self::ALL as $b) {
            $out[$b] = self::label($b);
        }

        return $out;
    }
}
