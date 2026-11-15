<?php

namespace Database\Seeders;

use App\Models\SafeHaven;
use Illuminate\Database\Seeder;

/**
 * Approximate locations so the app works offline on day one.
 * Run `php artisan havens:import` to replace/extend them with exact OpenStreetMap data.
 */
class SafeHavenSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['police', 'Gulshan Police Station', 23.7934, 90.4146, '999'],
            ['police', 'Banani Police Station', 23.7937, 90.4030, '999'],
            ['police', 'Dhanmondi Police Station', 23.7510, 90.3770, '999'],
            ['police', 'Ramna Police Station', 23.7395, 90.3950, '999'],
            ['police', 'Tejgaon Police Station', 23.7650, 90.3925, '999'],
            ['police', 'Motijheel Police Station', 23.7330, 90.4180, '999'],
            ['police', 'Uttara West Police Station', 23.8680, 90.3830, '999'],
            ['police', 'Mohammadpur Police Station', 23.7610, 90.3590, '999'],
            ['police', 'Mirpur Model Police Station', 23.8040, 90.3680, '999'],
            ['police', 'Khilgaon Police Station', 23.7500, 90.4300, '999'],
            ['police', 'Shahbagh Police Station', 23.7390, 90.3960, '999'],
            ['hospital', 'Dhaka Medical College Hospital', 23.7256, 90.3978, null],
            ['hospital', 'Square Hospital (Panthapath)', 23.7530, 90.3836, null],
            ['hospital', 'United Hospital (Gulshan)', 23.8070, 90.4210, null],
            ['hospital', 'Evercare Hospital (Bashundhara)', 23.8103, 90.4283, null],
            ['hospital', 'Ibn Sina Hospital (Dhanmondi)', 23.7399, 90.3735, null],
            ['hospital', 'Shaheed Suhrawardy Medical College Hospital', 23.7710, 90.3680, null],
            ['hospital', 'Uttara Crescent Hospital', 23.8740, 90.3980, null],
            ['hospital', 'Mugda Medical College Hospital', 23.7330, 90.4330, null],
            ['pharmacy', '24/7 Pharmacy - Gulshan 1', 23.7800, 90.4160, null],
            ['pharmacy', '24/7 Pharmacy - Dhanmondi 27', 23.7480, 90.3720, null],
            ['pharmacy', '24/7 Pharmacy - Uttara Sector 7', 23.8700, 90.4000, null],
            ['pharmacy', '24/7 Pharmacy - Mirpur 10', 23.8070, 90.3680, null],
            ['pharmacy', '24/7 Pharmacy - Banani', 23.7920, 90.4050, null],
        ];
        foreach ($rows as [$type, $name, $lat, $lng, $phone]) {
            SafeHaven::firstOrCreate(['name' => $name, 'type' => $type], ['lat' => $lat, 'lng' => $lng, 'phone' => $phone]);
        }
    }
}
