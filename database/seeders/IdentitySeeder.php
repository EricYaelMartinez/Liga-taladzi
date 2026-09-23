<?php

namespace Database\Seeders;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use Illuminate\Database\Seeder;

class IdentitySeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Administrador del sistema', 'slug' => 'system_admin', 'scope' => 'system', 'description' => 'Administra la plataforma completa.'],
            ['name' => 'Administrador de liga', 'slug' => 'league_admin', 'scope' => 'league', 'description' => 'Administra una liga autorizada.'],
            ['name' => 'Árbitro', 'slug' => 'referee', 'scope' => 'league', 'description' => 'Gestiona sus partidos y cédulas.'],
            ['name' => 'Representante de equipo', 'slug' => 'team_representative', 'scope' => 'league', 'description' => 'Administra equipos autorizados.'],
            ['name' => 'Jugador', 'slug' => 'player', 'scope' => 'league', 'description' => 'Consulta su perfil y actividad deportiva.'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['slug' => $role['slug']], [...$role, 'is_protected' => true]);
        }

        $permissions = [
            ['name' => 'Ver panel', 'slug' => 'dashboard.view', 'module' => 'identity'],
            ['name' => 'Ver usuarios', 'slug' => 'users.view', 'module' => 'identity'],
            ['name' => 'Crear usuarios', 'slug' => 'users.create', 'module' => 'identity'],
            ['name' => 'Modificar usuarios', 'slug' => 'users.update', 'module' => 'identity'],
            ['name' => 'Cambiar estado de usuarios', 'slug' => 'users.change-status', 'module' => 'identity'],
            ['name' => 'Restablecer contraseñas', 'slug' => 'users.reset-password', 'module' => 'identity'],
            ['name' => 'Eliminar usuarios', 'slug' => 'users.delete', 'module' => 'identity'],
            ['name' => 'Ver roles', 'slug' => 'roles.view', 'module' => 'identity'],
            ['name' => 'Asignar roles globales', 'slug' => 'roles.assign', 'module' => 'identity'],
        ];

        $permissionIds = collect($permissions)->map(function (array $permission): int {
            return Permission::updateOrCreate(
                ['slug' => $permission['slug']],
                [...$permission, 'description' => $permission['name']],
            )->id;
        });

        Role::where('slug', 'system_admin')->firstOrFail()->permissions()->sync($permissionIds);
    }
}
