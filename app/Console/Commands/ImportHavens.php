<?php

namespace App\Console\Commands;

use App\Models\SafeHaven;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ImportHavens extends Command
{
    protected $signature = 'havens:import {--city=Dhaka : City name as written in OpenStreetMap (name:en)}';

    protected $description = 'Import police stations, hospitals and 24/7 pharmacies from OpenStreetMap (Overpass API)';

    public function handle(): int
    {
        $city = $this->option('city');
        $query = <<<Q
[out:json][timeout:90];
area["name:en"="{$city}"]["boundary"="administrative"]->.a;
(
  nwr["amenity"~"^(police|hospital)$"](area.a);
  nwr["amenity"="pharmacy"]["opening_hours"="24/7"](area.a);
);
out center;
Q;
        $this->info("Querying Overpass for {$city} ...");
        $res = Http::timeout(120)->asForm()->post('https://overpass-api.de/api/interpreter', ['data' => $query]);
        if (! $res->successful()) {
            $this->error('Overpass request failed (HTTP '.$res->status().'). Try again in a minute.');

            return self::FAILURE;
        }

        $n = 0;
        foreach ($res->json('elements') ?? [] as $el) {
            $lat = $el['lat'] ?? ($el['center']['lat'] ?? null);
            $lng = $el['lon'] ?? ($el['center']['lon'] ?? null);
            $tags = $el['tags'] ?? [];
            if ($lat === null || $lng === null) {
                continue;
            }
            $type = $tags['amenity'] ?? 'police';
            SafeHaven::updateOrCreate(
                ['osm_id' => $el['type'].'/'.$el['id']],
                [
                    'type' => $type,
                    'name' => mb_substr($tags['name:en'] ?? $tags['name'] ?? ucfirst($type), 0, 150),
                    'lat' => $lat,
                    'lng' => $lng,
                    'phone' => isset($tags['phone']) ? mb_substr($tags['phone'], 0, 30) : null,
                ]
            );
            $n++;
        }
        $this->info("Imported/updated {$n} help points.");

        return self::SUCCESS;
    }
}
