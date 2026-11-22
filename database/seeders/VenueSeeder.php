<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Seeder;

/**
 * 40 FICTIONAL demo venues placed around real Dhaka areas (area centres are approximate).
 * Names are invented on purpose: we never attach demo incidents to real businesses.
 */
class VenueSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(2026);
        $owner = User::where('email', 'partner@test.com')->first();

        $areas = [
            'Gulshan' => [23.7925, 90.4078], 'Banani' => [23.7940, 90.4043], 'Dhanmondi' => [23.7461, 90.3742],
            'Uttara' => [23.8759, 90.3795], 'Mirpur' => [23.8069, 90.3687], 'Mohammadpur' => [23.7662, 90.3586],
            'Bashundhara' => [23.8130, 90.4250], 'Motijheel' => [23.7330, 90.4172],
        ];
        $kinds = [
            ['rooftop', 'Skyline Rooftop', 3, 'view,quiet,music', 'Open-air rooftop with city view and evening music.'],
            ['cafe', 'Midnight Cafe', 2, 'quiet,coffee,study', 'Calm late-night cafe for coffee and long conversations.'],
            ['eatery', 'Late Night Biryani House', 1, 'late-night food,family', 'Busy late-night eatery famous for biryani and kebab.'],
            ['restaurant', 'Family Garden Restaurant', 2, 'family,quiet', 'Family restaurant with garden seating.'],
            ['event', 'Open Mic Courtyard', 2, 'music,live', 'Weekend open-mic and acoustic events.'],
        ];
        $adj = ['Moon', 'Star', 'Lotus', 'Monsoon', 'Padma', 'Neon', 'Golden', 'Blue', 'River', 'Urban'];

        $i = 0;
        foreach ($areas as $area => [$lat, $lng]) {
            foreach (range(0, 4) as $k) {
                [$cat, $label, $tier, $tags, $desc] = $kinds[$k];
                $name = "{$adj[($i * 3 + $k) % 10]} {$label} ({$area})";
                // spread venues ~ up to 700 m around the area centre
                $vLat = $lat + (mt_rand(-60, 60) / 10000);
                $vLng = $lng + (mt_rand(-60, 60) / 10000);
                $cctv = mt_rand(0, 100) > 40;
                $guard = mt_rand(0, 100) > 45;
                $light = mt_rand(2, 5);
                Venue::create([
                    'owner_id' => $owner?->id,
                    'name' => $name, 'category' => $cat, 'area' => $area, 'price_tier' => $tier,
                    'lat' => round($vLat, 7), 'lng' => round($vLng, 7),
                    'opens_at' => '17:00', 'closes_at' => $cat === 'eatery' ? '04:00' : '01:00',
                    'has_cctv' => $cctv, 'has_guard' => $guard, 'lighting' => $light,
                    'is_restricted' => false, 'status' => 'approved',
                    'vibe_tags' => $tags, 'description' => $desc.' (Fictional demo venue.)',
                    'source' => 'demo',
                    'source_id' => 'demo/venue/'.$i.'-'.$k,
                    'verification_level' => 'demo',
                    'imported_at' => now(),
                ]);
                $i++;
            }
        }

        // a few age-restricted lounges (hidden until the user confirms they are 18+)
        foreach ([['Gulshan', 23.7931, 90.4090], ['Banani', 23.7952, 90.4021]] as $n => [$a, $la, $ln]) {
            Venue::create([
                'owner_id' => $owner?->id, 'name' => 'Velvet Lounge '.($n + 1)." ({$a})", 'category' => 'lounge', 'area' => $a, 'price_tier' => 3,
                'lat' => $la, 'lng' => $ln, 'opens_at' => '19:00', 'closes_at' => '02:00', 'has_cctv' => true, 'has_guard' => true, 'lighting' => 4,
                'is_restricted' => true, 'status' => 'approved', 'vibe_tags' => 'music,quiet', 'description' => 'Age-restricted lounge. (Fictional demo venue.)',
                'source' => 'demo', 'source_id' => 'demo/lounge/'.$n, 'verification_level' => 'demo', 'imported_at' => now(),
            ]);
        }
    }
}
