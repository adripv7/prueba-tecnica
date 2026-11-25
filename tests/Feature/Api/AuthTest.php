<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use DatabaseTransactions;

    public function test_login_validates_required_fields(): void
    {
        $this->postJson('/api/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_rejects_incorrect_credentials(): void
    {
        User::factory()->create([
            'email' => 'vendedor@example.test',
            'password' => Hash::make('correct-password'),
        ]);

        $this->postJson('/api/login', [
            'email' => 'vendedor@example.test',
            'password' => 'wrong-password',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'El correo o la contraseña son incorrectos.');
    }

    public function test_login_issues_a_token_that_can_access_protected_routes(): void
    {
        User::factory()->create([
            'email' => 'vendedor@example.test',
            'password' => Hash::make('correct-password'),
        ]);

        $login = $this->postJson('/api/login', [
            'email' => 'vendedor@example.test',
            'password' => 'correct-password',
            'device_name' => 'auth-test',
        ])
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.email', 'vendedor@example.test');

        $token = $login->json('access_token');
        $this->assertNotEmpty($token);

        $this->withToken($token)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('user.email', 'vendedor@example.test');
    }

    public function test_seeded_admin_can_login_and_access_protected_routes_as_admin(): void
    {
        $login = $this->postJson('/api/login', [
            'email' => 'admin@ventaslite.test',
            'password' => 'password',
            'device_name' => 'admin-seed-test',
        ])
            ->assertOk()
            ->assertJsonPath('user.email', 'admin@ventaslite.test')
            ->assertJsonPath('user.role', 'admin');

        $token = $login->json('access_token');
        $this->assertNotEmpty($token);

        $this->withToken($token)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('user.email', 'admin@ventaslite.test')
            ->assertJsonPath('user.role', 'admin');
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create([
            'email' => 'vendedor@example.test',
            'password' => Hash::make('correct-password'),
        ]);

        $login = $this->postJson('/api/login', [
            'email' => 'vendedor@example.test',
            'password' => 'correct-password',
        ])->assertOk();

        $token = $login->json('access_token');

        $this->withToken($token)
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Sesión cerrada correctamente.');

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => $user->getMorphClass(),
        ]);

        // El guard puede conservar en memoria al usuario de la petición anterior.
        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/user')
            ->assertUnauthorized();
    }

    public function test_login_is_limited_after_five_failed_attempts_in_one_minute(): void
    {
        $credentials = [
            'email' => 'vendedor@example.test',
            'password' => 'wrong-password',
        ];

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/login', $credentials)->assertUnauthorized();
        }

        $response = $this->postJson('/api/login', $credentials)
            ->assertTooManyRequests()
            ->assertJsonStructure(['retry_after_seconds']);

        $retryAfter = $response->json('retry_after_seconds');
        $this->assertGreaterThan(0, $retryAfter);
        $this->assertLessThanOrEqual(60, $retryAfter);
    }
}
