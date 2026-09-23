<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\ResetUserPasswordRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->toString();

        $users = User::query()
            ->with('roles:id,name,slug')
            ->when($search, fn ($query) => $query->where(function ($nested) use ($search): void {
                $normalizedSearch = mb_strtolower($search);
                $nested->whereRaw('LOWER(name) LIKE ?', ["%{$normalizedSearch}%"])
                    ->orWhereRaw('LOWER(email) LIKE ?', ["%{$normalizedSearch}%"]);
            }))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => ['search' => $search, 'status' => $status],
            'statuses' => $this->statuses(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Users/Create', [
            'statuses' => $this->statuses(),
            'roles' => $this->systemRoles(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        if ($request->filled('role_id')) {
            abort_unless($request->user()->hasPermission('roles.assign'), 403);
        }

        DB::transaction(function () use ($request): void {
            $data = $request->safe()->except(['role_id']);
            $data['email'] = Str::lower($data['email']);
            $data['force_password_change'] = true;
            $user = User::create($data);

            if ($request->filled('role_id')) {
                $user->roles()->sync([$request->integer('role_id')]);
            }
        });

        return redirect()->route('admin.users.index')->with('success', 'Usuario creado correctamente.');
    }

    public function edit(User $user): Response
    {
        $user->load('roles:id,name,slug');

        return Inertia::render('Admin/Users/Edit', [
            'managedUser' => $user,
            'statuses' => $this->statuses(),
            'roles' => $this->systemRoles(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();

        if ($validated['status'] !== $user->status->value) {
            abort_unless($request->user()->hasPermission('users.change-status'), 403);
        }

        $currentRoleId = $user->roles()->where('scope', 'system')->value('roles.id');
        if ((int) ($validated['role_id'] ?? 0) !== (int) ($currentRoleId ?? 0)) {
            abort_unless($request->user()->hasPermission('roles.assign'), 403);
        }

        if ($request->user()->is($user) && $validated['status'] !== UserStatus::Active->value) {
            return back()->withErrors(['status' => 'No puedes suspender o desactivar tu propia cuenta.']);
        }

        $systemAdminRoleId = Role::where('slug', 'system_admin')->value('id');
        if ($request->user()->is($user) && (int) ($validated['role_id'] ?? 0) !== (int) $systemAdminRoleId) {
            return back()->withErrors(['role_id' => 'No puedes retirar de tu propia cuenta el rol de administrador del sistema.']);
        }

        DB::transaction(function () use ($request, $user): void {
            $data = $request->safe()->except(['role_id']);
            $data['email'] = Str::lower($data['email']);
            $user->update($data);
            $user->roles()->sync($request->filled('role_id') ? [$request->integer('role_id')] : []);
        });

        return redirect()->route('admin.users.index')->with('success', 'Usuario actualizado correctamente.');
    }

    public function resetPassword(ResetUserPasswordRequest $request, User $user): RedirectResponse
    {
        $user->update([
            'password' => $request->validated('password'),
            'force_password_change' => true,
            'remember_token' => Str::random(60),
        ]);

        return back()->with('success', 'Contraseña restablecida. El usuario deberá cambiarla al ingresar.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('users.delete'), 403);

        if ($request->user()->is($user)) {
            return back()->withErrors(['user' => 'No puedes eliminar tu propia cuenta.']);
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'Usuario eliminado de forma segura.');
    }

    private function statuses(): array
    {
        return collect(UserStatus::cases())
            ->map(fn (UserStatus $status) => ['value' => $status->value, 'label' => $status->label()])
            ->all();
    }

    private function systemRoles(): array
    {
        return Role::where('scope', 'system')->orderBy('name')->get(['id', 'name', 'slug'])->all();
    }
}
