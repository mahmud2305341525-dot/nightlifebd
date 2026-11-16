<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_sixth_contact_is_rejected(): void
    {
        $user = $this->userWithRole('user');
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)->post('/contacts', ['name' => "C$i", 'phone' => '0171234567'.$i])->assertSessionHasNoErrors();
        }
        $this->actingAs($user)->post('/contacts', ['name' => 'Six', 'phone' => '01712345679'])->assertSessionHasErrors('phone');
        $this->assertDatabaseCount('emergency_contacts', 5);
    }

    public function test_invalid_phone_is_rejected(): void
    {
        $user = $this->userWithRole('user');
        $this->actingAs($user)->post('/contacts', ['name' => 'X', 'phone' => '12345'])->assertSessionHasErrors('phone');
    }

    public function test_user_cannot_delete_someone_elses_contact(): void
    {
        $a = $this->userWithRole('user');
        $b = $this->userWithRole('user');
        $c = $a->emergencyContacts()->create(['name' => 'Mom', 'phone' => '01712345678']);
        $this->actingAs($b)->delete('/contacts/'.$c->id)->assertForbidden();
    }

    public function test_duplicate_normalized_phone_is_rejected_for_same_user(): void
    {
        $user = $this->userWithRole('user');
        $this->actingAs($user)->post('/contacts', [
            'name' => 'Brother',
            'phone' => '01712345678',
        ])->assertSessionHasNoErrors();

        // Trying to register the exact same number with different formatting (+8801712-345678)
        $this->actingAs($user)->post('/contacts', [
            'name' => 'Brother Again',
            'phone' => '+8801712-345678',
        ])->assertSessionHasErrors('phone');

        $this->assertDatabaseCount('emergency_contacts', 1);
    }

    public function test_different_users_can_register_the_same_emergency_contact_phone(): void
    {
        $userA = $this->userWithRole('user');
        $userB = $this->userWithRole('user');

        $this->actingAs($userA)->post('/contacts', [
            'name' => 'Dad',
            'phone' => '01712345678',
        ])->assertSessionHasNoErrors();

        // Different user can also register the same contact phone
        $this->actingAs($userB)->post('/contacts', [
            'name' => 'Father',
            'phone' => '01712345678',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('emergency_contacts', 2);
    }
}
