<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Phase0ConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_roles_only(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(5, Role::count());
        $this->assertTrue(Role::where('name', 'event_organizer')->exists());
        $this->assertSame(0, User::count());
    }

    public function test_demo_seeding_is_disabled_by_default(): void
    {
        config(['nightlife.demo_enabled' => false]);
        $this->seed(DemoDataSeeder::class);

        $this->assertSame(0, User::count());
    }

    public function test_demo_seeding_is_forbidden_in_production(): void
    {
        config(['nightlife.demo_enabled' => true]);
        app()->detectEnvironment(fn () => 'production');

        $this->expectException(\RuntimeException::class);
        app(DemoDataSeeder::class)->run();
    }

    public function test_admin_create_command_creates_super_admin(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->artisan('admin:create', [
            '--email' => 'real-admin@example.com',
            '--name' => 'Real Admin',
        ])
            ->expectsQuestion('Password (minimum 12 characters)', 'a-strong-password')
            ->assertSuccessful();

        $user = User::where('email', 'real-admin@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('super_admin'));
    }

    public function test_forgot_password_view_has_no_direct_reset_link(): void
    {
        $this->get('/forgot-password')
            ->assertOk()
            ->assertDontSee('Direct Reset Link')
            ->assertDontSee('demo_reset_link');
    }
}
