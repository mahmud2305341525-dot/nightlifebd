<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EventSeeder extends Seeder
{
    public function run(): void
    {
        $organizer = User::where('email', 'admin@nightlifebd.com')->first()
            ?? User::first();

        if (! $organizer) {
            return;
        }

        $bashundharaVenue = Venue::where('area', 'Bashundhara')->first();
        $dhanmondiVenue = Venue::where('area', 'Dhanmondi')->first();
        $gulshanVenue = Venue::where('area', 'Gulshan')->first();
        $bananiVenue = Venue::where('area', 'Banani')->first();
        $motijheelVenue = Venue::where('area', 'Motijheel')->first();

        $events = [
            [
                'title' => 'Dhaka Rock Surge 2026: The Underground Revival',
                'category' => 'concert',
                'venue_id' => $bashundharaVenue?->id,
                'area' => 'Bashundhara',
                'address' => 'Convention Hall 3, Bashundhara R/A, Dhaka',
                'lat' => 23.8150,
                'lng' => 90.4280,
                'starts_at' => now()->addDays(2)->setHour(19)->setMinute(0),
                'ends_at' => now()->addDays(2)->setHour(23)->setMinute(30),
                'description' => "Dhaka's largest rock festival bringing 6 legendary and underground indie metal & alternative bands under one roof. Equipped with active security gates, verified emergency exits, and NightLifeBD Safe Route corridors.",
                'is_featured' => true,
                'tiers' => [
                    ['name' => 'Early Bird Access', 'price' => 500.00, 'capacity' => 150, 'max_per_user' => 4],
                    ['name' => 'General Admission', 'price' => 800.00, 'capacity' => 500, 'max_per_user' => 5],
                    ['name' => 'VIP Front Row & Backstage', 'price' => 2200.00, 'capacity' => 60, 'max_per_user' => 2],
                ],
            ],
            [
                'title' => 'Winter Acoustic & Rabindra-Fusion Night',
                'category' => 'live_music',
                'venue_id' => $dhanmondiVenue?->id,
                'area' => 'Dhanmondi',
                'address' => 'Lake Amphitheater, Road 8/A, Dhanmondi, Dhaka',
                'lat' => 23.7480,
                'lng' => 90.3750,
                'starts_at' => now()->addDay()->setHour(18)->setMinute(30),
                'ends_at' => now()->addDay()->setHour(22)->setMinute(0),
                'description' => 'An open-air evening of soothing acoustic guitars, flute harmonies, and soulful indie Bengali songs by the water. Family-friendly with continuous lighting along the lake promenade.',
                'is_featured' => true,
                'tiers' => [
                    ['name' => 'Free Community RSVP', 'price' => 0.00, 'capacity' => 250, 'max_per_user' => 4],
                    ['name' => 'Reserved Front Seat Pass', 'price' => 300.00, 'capacity' => 80, 'max_per_user' => 3],
                ],
            ],
            [
                'title' => 'Gulshan Sunset Jazz & Blues Session',
                'category' => 'live_music',
                'venue_id' => $gulshanVenue?->id,
                'area' => 'Gulshan',
                'address' => 'Rooftop Terrace, Road 11, Gulshan-2, Dhaka',
                'lat' => 23.7930,
                'lng' => 90.4085,
                'starts_at' => now()->addDays(4)->setHour(20)->setMinute(0),
                'ends_at' => now()->addDays(4)->setHour(23)->setMinute(45),
                'description' => 'International and local jazz musicians performing live improvisations. Premium rooftop seating with 360-degree skyline views of Dhaka.',
                'is_featured' => false,
                'tiers' => [
                    ['name' => 'Standard Entry', 'price' => 1200.00, 'capacity' => 100, 'max_per_user' => 4],
                    ['name' => 'Couple Pass (Includes 2 Mocktails)', 'price' => 2000.00, 'capacity' => 40, 'max_per_user' => 1],
                ],
            ],
            [
                'title' => 'Puran Dhaka Street Food & Heritage Night Market',
                'category' => 'night_market',
                'venue_id' => $motijheelVenue?->id,
                'area' => 'Motijheel',
                'address' => 'Heritage Plaza, Near Ahsan Manzil corridor, Old Dhaka',
                'lat' => 23.7120,
                'lng' => 90.4010,
                'starts_at' => now()->addDays(3)->setHour(17)->setMinute(0),
                'ends_at' => now()->addDays(3)->setHour(23)->setMinute(0),
                'description' => 'Experience the legendary authentic kebabs, bakarkhani, biryani, and live folk music of Puran Dhaka with safe night illumination and designated patrol stations.',
                'is_featured' => true,
                'tiers' => [
                    ['name' => 'Foodie Pass (Includes 3 Tastings)', 'price' => 350.00, 'capacity' => 400, 'max_per_user' => 5],
                    ['name' => 'Family Festival Pass (4 Persons)', 'price' => 1100.00, 'capacity' => 75, 'max_per_user' => 2],
                ],
            ],
            [
                'title' => 'ElectroPulse Dhaka: Midnight Synthwave & Bass',
                'category' => 'concert',
                'venue_id' => $bananiVenue?->id,
                'area' => 'Banani',
                'address' => 'Club 11 Arena, Road 11, Banani, Dhaka',
                'lat' => 23.7950,
                'lng' => 90.4030,
                'starts_at' => now()->addDays(5)->setHour(21)->setMinute(0),
                'ends_at' => now()->addDays(6)->setHour(2)->setMinute(0),
                'description' => 'Electronic music producers, live synth performances, and immersive laser art. Late-night event backed by NightLifeBD Walk With Me emergency circle check-ins.',
                'is_featured' => false,
                'tiers' => [
                    ['name' => 'General Pass', 'price' => 1500.00, 'capacity' => 200, 'max_per_user' => 4],
                    ['name' => 'VIP Lounge Access', 'price' => 3200.00, 'capacity' => 30, 'max_per_user' => 2],
                ],
            ],
        ];

        foreach ($events as $item) {
            $tiers = $item['tiers'];
            unset($item['tiers']);

            $item['slug'] = Str::slug($item['title']);
            $item['organizer_id'] = $organizer->id;
            $item['status'] = 'published';

            $event = Event::updateOrCreate(
                ['slug' => $item['slug']],
                $item
            );

            // Seed tiers
            foreach ($tiers as $tier) {
                $event->ticketTiers()->updateOrCreate(
                    ['name' => $tier['name']],
                    $tier
                );
            }
        }
    }
}
