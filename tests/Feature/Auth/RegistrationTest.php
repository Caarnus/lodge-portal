<?php

namespace Tests\Feature\Auth;

use App\Models\Lodge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register()
    {
        $lodge = Lodge::factory()->create();
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'home_lodge_id' => $lodge->id,
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('pending', absolute: false));
        $this->assertDatabaseHas('users', ['email' => 'test@example.com', 'home_lodge_id' => $lodge->id, 'approval_status' => 'pending']);
    }
}
