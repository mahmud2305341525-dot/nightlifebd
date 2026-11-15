<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            VenueSeeder::class,
            SafeHavenSeeder::class,
            DemoReportSeeder::class,
        ]);
    }
}
