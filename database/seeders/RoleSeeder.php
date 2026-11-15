<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['user', 'venue_partner', 'safety_admin', 'super_admin'] as $r) {
            Role::findOrCreate($r);
        }

        // Local development accounts only - change or delete before any real deployment.
        $accounts = [
            ['Test User', 'user@test.com', 'user'],
            ['Test Partner', 'partner@test.com', 'venue_partner'],
            ['Test Safety', 'safety@test.com', 'safety_admin'],
            ['Test Admin', 'admin@test.com', 'super_admin'],
        ];
        foreach ($accounts as [$name, $email, $role]) {
            $u = User::firstOrCreate(['email' => $email], [
                'name' => $name,
                'password' => Hash::make('password'),
                'is_adult_confirmed' => true,
                'email_verified_at' => now(),
            ]);
            $u->syncRoles([$role]);
        }

        // a few extra reporters with different trust levels for the demo data
        foreach ([['Rafi', 0.85], ['Nusrat', 0.90], ['Tanvir', 0.70], ['Mim', 0.60], ['Sakib', 0.50], ['Anika', 0.80]] as [$n, $t]) {
            $u = User::firstOrCreate(['email' => strtolower($n).'@demo.test'], [
                'name' => $n, 'password' => Hash::make('password'), 'trust_score' => $t, 'is_adult_confirmed' => true, 'email_verified_at' => now(),
            ]);
            $u->syncRoles(['user']);
        }
    }
}
