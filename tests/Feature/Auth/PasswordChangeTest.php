<?php

namespace Tests\Feature\Auth;

use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_temporary_password_is_forced_to_change_it(): void
    {
        $user = User::factory()->create(['force_password_change' => true]);

        $this->actingAs($user)->get('/panel')->assertRedirect('/cambiar-contrasena');
    }

    public function test_user_can_change_temporary_password(): void
    {
        $user = User::factory()->create([
            'password' => 'OldSecurePassword123',
            'force_password_change' => true,
        ]);

        $this->actingAs($user)->put('/cambiar-contrasena', [
            'current_password' => 'OldSecurePassword123',
            'password' => 'NewSecurePassword456',
            'password_confirmation' => 'NewSecurePassword456',
        ])->assertRedirect('/panel');

        $user->refresh();
        $this->assertFalse($user->force_password_change);
        $this->assertTrue(Hash::check('NewSecurePassword456', $user->password));
    }
}
