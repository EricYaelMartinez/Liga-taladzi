<?php

namespace Tests\Feature\Auth;

use App\Domain\Identity\Models\AuthenticationEvent;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_log_in_and_event_is_recorded(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.test',
            'password' => 'SecurePassword123',
        ]);

        $response = $this->post('/iniciar-sesion', [
            'email' => 'ADMIN@example.test',
            'password' => 'SecurePassword123',
        ]);

        $response->assertRedirect('/panel');
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas(AuthenticationEvent::class, [
            'user_id' => $user->id,
            'successful' => true,
        ]);
    }

    public function test_suspended_user_cannot_log_in(): void
    {
        $user = User::factory()->suspended()->create([
            'email' => 'suspended@example.test',
            'password' => 'SecurePassword123',
        ]);

        $this->post('/iniciar-sesion', [
            'email' => $user->email,
            'password' => 'SecurePassword123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseHas(AuthenticationEvent::class, [
            'user_id' => $user->id,
            'successful' => false,
            'failure_reason' => 'inactive_account',
        ]);
    }

    public function test_guest_is_redirected_from_dashboard(): void
    {
        $this->get('/panel')->assertRedirect('/iniciar-sesion');
    }

    public function test_user_can_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/cerrar-sesion')->assertRedirect('/iniciar-sesion');
        $this->assertGuest();
    }
}
