<?php

namespace Tests\Feature\Admin;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IdentitySeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::where('slug', 'system_admin')->firstOrFail());
    }

    public function test_system_administrator_can_view_users(): void
    {
        $this->actingAs($this->admin)
            ->get('/administracion/usuarios')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Users/Index'));
    }

    public function test_user_without_permission_cannot_view_users(): void
    {
        $regularUser = User::factory()->create();

        $this->actingAs($regularUser)->get('/administracion/usuarios')->assertForbidden();
    }

    public function test_update_permission_does_not_allow_status_or_role_changes(): void
    {
        $limitedRole = Role::create([
            'name' => 'Editor limitado',
            'slug' => 'limited_editor',
            'scope' => 'system',
            'is_protected' => false,
        ]);
        $limitedRole->permissions()->sync([
            Permission::where('slug', 'users.update')->firstOrFail()->id,
        ]);
        $limitedUser = User::factory()->create();
        $limitedUser->roles()->attach($limitedRole);
        $managedUser = User::factory()->create();

        $this->actingAs($limitedUser)->put("/administracion/usuarios/{$managedUser->id}", [
            'name' => $managedUser->name,
            'email' => $managedUser->email,
            'phone' => '',
            'status' => UserStatus::Suspended->value,
            'role_id' => null,
        ])->assertForbidden();
    }

    public function test_administrator_can_create_user_with_temporary_password(): void
    {
        $role = Role::where('slug', 'system_admin')->firstOrFail();

        $this->actingAs($this->admin)->post('/administracion/usuarios', [
            'name' => 'Nueva Administradora',
            'email' => 'NUEVA@example.test',
            'phone' => '555 000 0000',
            'status' => UserStatus::Active->value,
            'role_id' => $role->id,
            'password' => 'TemporaryPassword123',
            'password_confirmation' => 'TemporaryPassword123',
        ])->assertRedirect('/administracion/usuarios');

        $user = User::where('email', 'nueva@example.test')->firstOrFail();
        $this->assertTrue($user->force_password_change);
        $this->assertTrue(Hash::check('TemporaryPassword123', $user->password));
        $this->assertTrue($user->hasRole('system_admin'));
    }

    public function test_administrator_cannot_suspend_or_demote_own_account(): void
    {
        $payload = [
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'phone' => '',
            'status' => UserStatus::Suspended->value,
            'role_id' => null,
        ];

        $this->actingAs($this->admin)
            ->put("/administracion/usuarios/{$this->admin->id}", $payload)
            ->assertSessionHasErrors('status');

        $payload['status'] = UserStatus::Active->value;
        $this->actingAs($this->admin)
            ->put("/administracion/usuarios/{$this->admin->id}", $payload)
            ->assertSessionHasErrors('role_id');
    }

    public function test_deletion_is_logical_and_self_deletion_is_rejected(): void
    {
        $managedUser = User::factory()->create();

        $this->actingAs($this->admin)
            ->delete("/administracion/usuarios/{$managedUser->id}")
            ->assertRedirect('/administracion/usuarios');
        $this->assertSoftDeleted($managedUser);

        $this->actingAs($this->admin)
            ->delete("/administracion/usuarios/{$this->admin->id}")
            ->assertSessionHasErrors('user');
        $this->assertNotSoftDeleted($this->admin);
    }
}
