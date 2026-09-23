<?php

namespace App\Console\Commands;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use Database\Seeders\IdentitySeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateInitialAdmin extends Command
{
    protected $signature = 'liga:create-admin {--name=} {--email=}';
    protected $description = 'Crea o actualiza el administrador inicial sin guardar contraseñas en el código.';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Nombre completo');
        $email = mb_strtolower($this->option('email') ?: $this->ask('Correo electrónico'));
        $password = $this->secret('Contraseña (mínimo 12 caracteres, mayúsculas, minúsculas y números)');
        $confirmation = $this->secret('Confirma la contraseña');

        $validator = Validator::make(compact('name', 'email', 'password', 'confirmation'), [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'same:confirmation', Password::min(12)->letters()->mixedCase()->numbers()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        $this->call('db:seed', ['--class' => IdentitySeeder::class, '--force' => true]);

        $user = User::withTrashed()->firstOrNew(['email' => $email]);
        $user->fill([
            'name' => $name,
            'password' => $password,
            'status' => UserStatus::Active,
            'force_password_change' => false,
        ]);
        $user->deleted_at = null;
        $user->save();
        $user->roles()->sync([Role::where('slug', 'system_admin')->firstOrFail()->id]);

        $this->info('Administrador preparado correctamente.');
        return self::SUCCESS;
    }
}
