<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/admin/reports')->assertRedirect('/login');
        $this->get('/contacts')->assertRedirect('/login');
    }

    public function test_normal_user_cannot_open_admin_or_partner_pages(): void
    {
        $u = $this->userWithRole('user');
        $this->actingAs($u)->get('/admin/venues')->assertForbidden();
        $this->actingAs($u)->get('/admin/reports')->assertForbidden();
        $this->actingAs($u)->get('/partner/venues/create')->assertForbidden();
        $this->actingAs($u)->get('/admin/users')->assertForbidden();
    }

    public function test_staff_roles_can_open_their_pages(): void
    {
        $this->actingAs($this->userWithRole('safety_admin'))->get('/admin/reports')->assertOk();
        $this->actingAs($this->userWithRole('venue_partner'))->get('/partner/venues/create')->assertOk();
        $this->actingAs($this->userWithRole('super_admin'))->get('/admin/users')->assertOk();
        $this->actingAs($this->userWithRole('safety_admin'))->get('/admin/users')->assertForbidden();
    }

    public function test_banned_user_is_blocked(): void
    {
        $u = $this->userWithRole('user', ['is_banned' => true]);
        $this->actingAs($u)->get('/contacts')->assertForbidden();
    }
}
