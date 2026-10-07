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
            ['name' => 'Ver temporadas y competencias', 'slug' => 'competitions.view', 'module' => 'competition'],
            ['name' => 'Administrar temporadas y competencias', 'slug' => 'competitions.manage', 'module' => 'competition'],
            ['name' => 'Ver equipos', 'slug' => 'teams.view', 'module' => 'team'],
            ['name' => 'Administrar equipos', 'slug' => 'teams.manage', 'module' => 'team'],
            ['name' => 'Proponer cambios de su equipo', 'slug' => 'teams.propose-update', 'module' => 'team'],
            ['name' => 'Ver jugadores y plantillas', 'slug' => 'players.view', 'module' => 'player'],
            ['name' => 'Proponer altas y bajas de jugadores', 'slug' => 'players.propose', 'module' => 'player'],
            ['name' => 'Administrar y aprobar jugadores', 'slug' => 'players.manage', 'module' => 'player'],
            ['name' => 'Actualizar datos propios de jugador', 'slug' => 'players.self-update', 'module' => 'player'],
            ['name' => 'Ver documentos privados de jugadores', 'slug' => 'documents.view', 'module' => 'document'],
            ['name' => 'Administrar documentos privados de jugadores', 'slug' => 'documents.manage', 'module' => 'document'],
            ['name' => 'Emitir y revocar credenciales', 'slug' => 'credentials.manage', 'module' => 'document'],
            ['name' => 'Ver campos y disponibilidad', 'slug' => 'fields.view', 'module' => 'scheduling'],
            ['name' => 'Administrar campos y disponibilidad', 'slug' => 'fields.manage', 'module' => 'scheduling'],
            ['name' => 'Ver árbitros y disponibilidad', 'slug' => 'referees.view', 'module' => 'referee'],
            ['name' => 'Administrar árbitros y observaciones', 'slug' => 'referees.manage', 'module' => 'referee'],
            ['name' => 'Ver jornadas y calendario', 'slug' => 'schedule.view', 'module' => 'scheduling'],
            ['name' => 'Administrar jornadas y programación', 'slug' => 'schedule.manage', 'module' => 'scheduling'],
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
            'competitions.view',
            'competitions.manage',
            'teams.view',
            'teams.manage',
            'players.view',
            'players.propose',
            'players.manage',
            'players.self-update',
            'documents.view',
            'documents.manage',
            'credentials.manage',
            'fields.view',
            'fields.manage',
            'referees.view',
            'referees.manage',
            'schedule.view',
            'schedule.manage',
            'audit.view',
        ])->pluck('id');
        Role::where('slug', 'league_admin')->firstOrFail()->permissions()->sync($leagueAdminPermissions);

        $dashboardPermission = Permission::where('slug', 'dashboard.view')->firstOrFail()->id;
        $refereePermissions = Permission::whereIn('slug', ['dashboard.view', 'referees.view', 'schedule.view'])->pluck('id');
        Role::where('slug', 'referee')->firstOrFail()->permissions()->sync($refereePermissions);
        $playerPermissions = Permission::whereIn('slug', [
            'dashboard.view', 'players.view', 'players.self-update', 'schedule.view',
        ])->pluck('id');
        Role::where('slug', 'player')->firstOrFail()->permissions()->sync($playerPermissions);
        $representativePermissions = Permission::whereIn('slug', [
            'dashboard.view', 'teams.view', 'teams.propose-update', 'players.view', 'players.propose', 'schedule.view',
        ])->pluck('id');
        Role::where('slug', 'team_representative')->firstOrFail()->permissions()->sync($representativePermissions);
    }
}
