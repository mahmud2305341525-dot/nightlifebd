<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GeocodingService
{
    public function search(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $cacheKey = 'nightlife:geocode:'.hash('sha256', mb_strtolower($query));

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($query) {
            try {
                $response = Http::timeout(10)
                    ->withHeaders([
                        'User-Agent' => 'NightLifeBD/1.0 (night-safety application)',
                        'Accept-Language' => 'bn,en',
                    ])
                    ->get('https://nominatim.openstreetmap.org/search', [
                        'q' => $query.', Dhaka, Bangladesh',
                        'format' => 'jsonv2',
                        'limit' => 5,
                        'countrycodes' => 'bd',
                        'viewbox' => '90.20,23.96,90.55,23.62',
                        'bounded' => 1,
                    ]);
                if (! $response->successful()) {
                    return [];
                }

                return collect($response->json())->map(fn (array $row) => [
                    'lat' => (float) $row['lat'],
                    'lng' => (float) $row['lon'],
                    'display_name' => (string) ($row['display_name'] ?? ''),
                    'source' => 'OpenStreetMap/Nominatim',
                ])->filter(fn ($row) => $row['lat'] >= 20 && $row['lat'] <= 27 && $row['lng'] >= 88 && $row['lng'] <= 93)->values()->all();
            } catch (\Throwable $e) {
                return [];
            }
        });
    }
}
