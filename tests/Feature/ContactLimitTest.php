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
}
