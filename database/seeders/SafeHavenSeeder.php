<?php

namespace Database\Seeders;

use App\Models\SafeHaven;
use Illuminate\Database\Seeder;

/**
 * 24/7 Safe Havens in Dhaka — police stations, 24-hour hospitals, and 24-hour pharmacies.
 * Sources: Bangladesh Police directory, OSM, hospital websites, Lazz Pharma branch list.
 * Run `php artisan havens:import` to extend with live OpenStreetMap data.
 *
 * Feature 3 — Dhaka Safe Haven Network (Capstone: NightLifeBD)
 */
class SafeHavenSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            // ── Police stations (all reachable via 999) ──────────────────────
            ['police', 'Gulshan Police Station',               23.7934, 90.4146, '999'],
            ['police', 'Banani Police Station',                23.7937, 90.4030, '999'],
            ['police', 'Dhanmondi Police Station',             23.7510, 90.3770, '999'],
            ['police', 'Ramna Police Station',                 23.7395, 90.3950, '999'],
            ['police', 'Tejgaon Police Station',               23.7650, 90.3925, '999'],
            ['police', 'Motijheel Police Station',             23.7330, 90.4180, '999'],
            ['police', 'Uttara West Police Station',           23.8680, 90.3830, '999'],
            ['police', 'Mohammadpur Police Station',           23.7610, 90.3590, '999'],
            ['police', 'Mirpur Model Police Station',          23.8040, 90.3680, '999'],
            ['police', 'Khilgaon Police Station',              23.7500, 90.4300, '999'],
            ['police', 'Shahbagh Police Station',              23.7390, 90.3960, '999'],
            ['police', 'Lalbagh Police Station',               23.7200, 90.3870, '999'],
            ['police', 'Wari Police Station',                  23.7180, 90.4100, '999'],
            ['police', 'Demra Police Station',                 23.7040, 90.4680, '999'],
            ['police', 'Vatara Police Station',                23.8190, 90.4310, '999'],
            ['police', 'Badda Police Station',                 23.7790, 90.4360, '999'],
            ['police', 'Kafrul Police Station',                23.7850, 90.3810, '999'],
            ['police', 'Turag Police Station',                 23.8510, 90.3700, '999'],
            ['police', 'Rayer Bazar Police Station',           23.7450, 90.3600, '999'],
            ['police', 'New Market Police Station',            23.7390, 90.3840, '999'],

            // ── 24-hour Emergency Hospitals ───────────────────────────────────
            ['hospital', 'Dhaka Medical College Hospital',           23.7256, 90.3978, '01969-000111'],
            ['hospital', 'Square Hospital (Panthapath)',              23.7530, 90.3836, '10616'],
            ['hospital', 'United Hospital (Gulshan)',                 23.8070, 90.4210, '10666'],
            ['hospital', 'Evercare Hospital (Bashundhara)',           23.8103, 90.4283, '10678'],
            ['hospital', 'Ibn Sina Hospital (Dhanmondi)',             23.7399, 90.3735, '10601'],
            ['hospital', 'Shaheed Suhrawardy Medical College Hospital', 23.7710, 90.3680, '01958-001770'],
            ['hospital', 'Uttara Crescent Hospital',                  23.8740, 90.3980, '01969-700047'],
            ['hospital', 'Mugda Medical College Hospital',            23.7330, 90.4330, '01817-006250'],
            ['hospital', 'National Institute of Traumatology (NITOR)', 23.7320, 90.3710, '01998-900017'],
            ['hospital', 'Mitford Hospital (Sir Salimullah)',         23.7145, 90.4030, '01969-000112'],
            ['hospital', 'Dhaka Shishu Hospital (Children)',          23.7420, 90.3800, '01969-000116'],
            ['hospital', 'Labaid Specialized Hospital (Dhanmondi)',   23.7460, 90.3730, '16345'],
            ['hospital', 'Popular Medical Centre (Shyamoli)',         23.7710, 90.3560, '01977-442200'],
            ['hospital', 'Anwer Khan Modern Medical College Hospital', 23.7580, 90.3750, '01969-003141'],
            ['hospital', 'Green Life Medical College Hospital',        23.7530, 90.3780, '01969-003131'],

            // ── 24/7 Pharmacies — Lazz Pharma, Medinova, Noorjahan ────────────
            ['pharmacy', 'Lazz Pharma — Gulshan 1 Circle',           23.7800, 90.4160, '16770'],
            ['pharmacy', 'Lazz Pharma — Gulshan 2',                  23.7933, 90.4163, '16770'],
            ['pharmacy', 'Lazz Pharma — Banani 11',                  23.7920, 90.4050, '16770'],
            ['pharmacy', 'Lazz Pharma — Dhanmondi 27',               23.7480, 90.3720, '16770'],
            ['pharmacy', 'Lazz Pharma — Uttara Sector 7',            23.8700, 90.4000, '16770'],
            ['pharmacy', 'Lazz Pharma — Mirpur 10',                  23.8070, 90.3680, '16770'],
            ['pharmacy', 'Lazz Pharma — Mohammadpur Town Hall',      23.7640, 90.3580, '16770'],
            ['pharmacy', 'Lazz Pharma — Shyamoli',                   23.7710, 90.3560, '16770'],
            ['pharmacy', 'Medinova Pharmacy — Malibagh',             23.7510, 90.4240, null],
            ['pharmacy', 'Medinova Pharmacy — Bashundhara',          23.8155, 90.4283, null],
            ['pharmacy', 'Noorjahan Pharmacy — Farmgate',            23.7580, 90.3895, null],
            ['pharmacy', '24/7 Pharmacy — Motijheel',                23.7330, 90.4185, null],
        ];

        foreach ($rows as [$type, $name, $lat, $lng, $phone]) {
            SafeHaven::firstOrCreate(
                ['name' => $name, 'type' => $type],
                ['lat' => $lat, 'lng' => $lng, 'phone' => $phone]
            );
        }

        $this->command->info('SafeHaven seeder: '.count($rows).' Dhaka safe havens upserted.');
    }
}
