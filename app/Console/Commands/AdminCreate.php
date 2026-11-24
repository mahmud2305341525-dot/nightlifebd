<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class AdminCreate extends Command
{
    protected $signature = 'admin:create {--email=} {--name=NightLifeBD Admin}';

    protected $description = 'Create the first real super administrator';

    public function handle(): int
    {
        $email = (string) ($this->option('email') ?: $this->ask('Administrator email'));
        $name = (string) $this->option('name');
        $password = (string) $this->secret('Password (minimum 12 characters)');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Enter a valid email address.');

            return self::FAILURE;
        }
        if (strlen($password) < 12) {
            $this->error('Password must be at least 12 characters.');

            return self::FAILURE;
        }
        if (User::where('email', $email)->exists()) {
            $this->error('A user with that email already exists.');

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
            'is_adult_confirmed' => true,
        ]);
        $user->assignRole('super_admin');

        $this->info("Created super administrator: {$email}");

        return self::SUCCESS;
    }
}
