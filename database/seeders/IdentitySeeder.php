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
            ['name' => 'Ver ligas', 'slug' => 'leagues.view', 'module' => 'league'],
            ['name' => 'Crear ligas', 'slug' => 'leagues.create', 'module' => 'league'],
            ['name' => 'Modificar ligas', 'slug' => 'leagues.update', 'module' => 'league'],
            ['name' => 'Eliminar ligas', 'slug' => 'leagues.delete', 'module' => 'league'],
            ['name' => 'Ver configuración de liga', 'slug' => 'league.settings.view', 'module' => 'league'],
            ['name' => 'Modificar configuración de liga', 'slug' => 'league.settings.update', 'module' => 'league'],
            ['name' => 'Ver miembros de liga', 'slug' => 'league.members.view', 'module' => 'league'],
            ['name' => 'Asignar miembros de liga', 'slug' => 'league.members.create', 'module' => 'league'],
            ['name' => 'Modificar accesos de liga', 'slug' => 'league.members.update', 'module' => 'league'],
            ['name' => 'Crear usuarios de liga', 'slug' => 'league.users.create', 'module' => 'league'],
            ['name' => 'Ver bitácora', 'slug' => 'audit.view', 'module' => 'audit'],
        ];

        $permissionIds = collect($permissions)->map(function (array $permission): int {
            return Permission::updateOrCreate(
                ['slug' => $permission['slug']],
                [...$permission, 'description' => $permission['name']],
            )->id;
        });

        Role::where('slug', 'system_admin')->firstOrFail()->permissions()->sync($permissionIds);

        $leagueAdminPermissions = Permission::whereIn('slug', [
            'dashboard.view',
            'league.settings.view',
            'league.settings.update',
            'league.members.view',
            'league.members.create',
            'league.members.update',
            'league.users.create',
            'audit.view',
        ])->pluck('id');
        Role::where('slug', 'league_admin')->firstOrFail()->permissions()->sync($leagueAdminPermissions);

        $dashboardPermission = Permission::where('slug', 'dashboard.view')->firstOrFail()->id;
        Role::whereIn('slug', ['referee', 'team_representative', 'player'])
            ->get()
            ->each(fn (Role $role) => $role->permissions()->sync([$dashboardPermission]));
    }
}
