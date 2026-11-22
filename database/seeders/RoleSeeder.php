<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['user', 'venue_partner', 'event_organizer', 'safety_admin', 'super_admin'] as $r) {
            Role::findOrCreate($r);
        }

    }
}
