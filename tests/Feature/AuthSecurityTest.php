<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * No self sign-up, and brute-force protection on the login.
 */
class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create([
            'email' => 'agent@example.com',
            'password' => Hash::make('secret-password'),
            'is_active' => true,
        ]);
    }

    private function login(string $password, string $email = 'agent@example.com')
    {
        return $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => $password]);
    }

    public function test_self_registration_is_gone(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Intrus',
            'email' => 'intrus@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'intrus@example.com']);
    }

    public function test_login_locks_after_four_wrong_passwords(): void
    {
        $this->user();

        foreach ([3, 2, 1, 0] as $left) {
            $this->login('wrong')->assertStatus(401)->assertJsonPath('errors.attempts_left', $left);
        }

        // Fifth try is refused, even with the right password
        $this->login('secret-password')
            ->assertStatus(429)
            ->assertJsonStructure(['errors' => ['retry_after']]);

        // …until the lock expires
        $this->travel(61)->seconds();
        $this->login('secret-password')->assertOk()->assertJsonStructure(['data' => ['token']]);
    }

    public function test_a_successful_login_resets_the_counter(): void
    {
        $this->user();

        $this->login('wrong')->assertStatus(401);
        $this->login('wrong')->assertStatus(401);
        $this->login('wrong')->assertStatus(401);
        $this->login('secret-password')->assertOk();

        $this->login('wrong')->assertStatus(401)->assertJsonPath('errors.attempts_left', 3);
    }

    public function test_the_lock_is_per_email(): void
    {
        $this->user();
        User::factory()->create(['email' => 'other@example.com', 'password' => Hash::make('other-password'), 'is_active' => true]);

        foreach (range(1, 4) as $i) {
            $this->login('wrong');
        }
        $this->login('secret-password')->assertStatus(429);

        // Another account from the same place is not locked
        $this->login('other-password', 'other@example.com')->assertOk();
    }
}
