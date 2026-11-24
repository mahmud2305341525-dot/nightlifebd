<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('nightlife.demo_enabled')) {
            $this->command?->error('Demo seeding is disabled. Set NIGHTLIFE_DEMO_ENABLED=true to enable it explicitly.');

            return;
        }

        if (app()->environment('production')) {
            throw new \RuntimeException('Demo seeding is forbidden in the production environment.');
        }

        $accounts = [
            ['Test User', 'user@test.com', 'user'],
            ['Test Partner', 'partner@test.com', 'venue_partner'],
            ['Test Safety', 'safety@test.com', 'safety_admin'],
            ['Test Admin', 'admin@test.com', 'super_admin'],
        ];

        foreach ($accounts as [$name, $email, $role]) {
            $user = User::firstOrCreate(['email' => $email], [
                'name' => $name,
                'password' => Hash::make('password'),
                'is_adult_confirmed' => true,
                'email_verified_at' => now(),
            ]);
            $user->syncRoles([$role]);
        }

        foreach ([['Rafi', 0.85], ['Nusrat', 0.90], ['Tanvir', 0.70], ['Mim', 0.60], ['Sakib', 0.50], ['Anika', 0.80]] as [$name, $trust]) {
            $user = User::firstOrCreate(['email' => strtolower($name).'@demo.test'], [
                'name' => $name,
                'password' => Hash::make('password'),
                'trust_score' => $trust,
                'is_adult_confirmed' => true,
                'email_verified_at' => now(),
            ]);
            $user->syncRoles(['user']);
        }

        $this->call([
            VenueSeeder::class,
            SafeHavenSeeder::class,
            DemoReportSeeder::class,
            EventSeeder::class,
        ]);
    }
}
