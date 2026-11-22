<?php

namespace App\Console\Commands;

use App\Models\Venue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class ImportVenues extends Command
{
    protected $signature = 'venues:import {--area= : Area name for display and filtering} {--dry-run : Fetch and report rows without saving}';

    protected $description = 'Import Dhaka public venues from OpenStreetMap via Overpass';

    public function handle(): int
    {
        $area = trim((string) ($this->option('area') ?: 'Dhaka Metropolitan'));
        $areaEscape = str_replace(['"', '\\'], ['', ''], $area);
        $query = <<<Q
[out:json][timeout:60][maxsize:128000000];
area["name:en"="{$areaEscape}"]["boundary"="administrative"]->.a;
(
  nwr["amenity"~"^(cafe|restaurant|fast_food|food_court)$"](area.a);
  nwr["amenity"="bar"](area.a);
  nwr["tourism"="viewpoint"](area.a);
);
out center tags;
Q;

        $this->info('Querying Overpass; source attribution: © OpenStreetMap contributors.');
        $response = Http::timeout(90)
            ->retry([1000, 3000])
            ->asForm()
            ->post('https://overpass-api.de/api/interpreter', ['data' => $query]);

        if (! $response->successful()) {
            $this->error('Overpass import failed (HTTP '.$response->status().').');

            return self::FAILURE;
        }

        $imported = 0;
        $elements = $response->json('elements') ?? [];
        foreach ($elements as $element) {
            $lat = $element['lat'] ?? ($element['center']['lat'] ?? null);
            $lng = $element['lon'] ?? ($element['center']['lon'] ?? null);
            $tags = $element['tags'] ?? [];
            $name = trim((string) ($tags['name:en'] ?? $tags['name'] ?? ''));
            if ($lat === null || $lng === null || $name === '') {
                continue;
            }

            $validator = Validator::make([
                'lat' => $lat,
                'lng' => $lng,
            ], [
                'lat' => ['numeric', 'between:20,27'],
                'lng' => ['numeric', 'between:88,93'],
            ]);
            if ($validator->fails()) {
                continue;
            }

            $amenity = $tags['amenity'] ?? null;
            $category = match ($amenity) {
                'cafe' => 'cafe',
                'bar' => 'lounge',
                'restaurant', 'fast_food', 'food_court' => 'restaurant',
                default => 'eatery',
            };
            $areaName = trim((string) ($tags['addr:suburb'] ?? $tags['addr:district'] ?? $area));
            $sourceId = ($element['type'] ?? 'node').'/'.($element['id'] ?? uniqid());
            $sourceUrl = 'https://www.openstreetmap.org/'.($element['type'] ?? 'node').'/'.($element['id'] ?? '');
            $description = trim((string) ($tags['description:en'] ?? $tags['description'] ?? ''));

            if ($this->option('dry-run')) {
                $this->line("{$sourceId} | {$name} | {$category} | {$areaName}");
                $imported++;

                continue;
            }

            Venue::updateOrCreate(
                ['source' => 'osm', 'source_id' => $sourceId],
                [
                    'name' => mb_substr($name, 0, 100),
                    'category' => $category,
                    'area' => mb_substr($areaName, 0, 60),
                    'price_tier' => 2,
                    'lat' => round((float) $lat, 7),
                    'lng' => round((float) $lng, 7),
                    'opens_at' => null,
                    'closes_at' => null,
                    'is_restricted' => $category === 'lounge',
                    'has_cctv' => null,
                    'has_guard' => null,
                    'lighting' => null,
                    'status' => 'approved',
                    'verification_level' => 'unverified',
                    'source_url' => $sourceUrl,
                    'imported_at' => now(),
                    'description' => mb_substr($description, 0, 1000),
                    'vibe_tags' => $this->deriveVibeTags($tags),
                ]
            );
            $imported++;
        }

        $this->info(($this->option('dry-run') ? 'Would import' : 'Imported')." {$imported} OSM venues.");

        return self::SUCCESS;
    }

    private function deriveVibeTags(array $tags): string
    {
        $values = [];
        foreach (['cafe' => 'coffee', 'restaurant' => 'family', 'bar' => 'music'] as $tag => $vibe) {
            if (isset($tags[$tag])) {
                $values[] = $vibe;
            }
        }

        return implode(',', array_values(array_unique($values)));
    }
}
