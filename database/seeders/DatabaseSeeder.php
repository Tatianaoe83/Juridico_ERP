<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Cuentas de arranque, una por rol.
     *
     * Sin superadmin nadie podría borrar usuarios ni administrar roles: a
     * `admin` se le dejan esos dos permisos fuera de alcance a propósito.
     */
    private const ACCOUNTS = [
        ['super@example.com', 'Super Admin', 'superadmin'],
        ['admin@example.com', 'Admin', 'admin'],
        ['test@example.com', 'Test User', 'user'],
    ];

    public function run(): void
    {
        $this->call(RoleSeeder::class);

        foreach (self::ACCOUNTS as [$email, $name, $role]) {
            $user = User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => 'password']);

            /*
             * El rol solo se asigna al crear la cuenta.
             *
             * Antes se hacía siempre, y volver a sembrar degradaba a quien
             * hubiera sido promovido desde la interfaz: el seeder pisaba una
             * decisión tomada en producción.
             */
            if ($user->wasRecentlyCreated || $user->roles->isEmpty()) {
                $user->syncRoles($role);
            }
        }
    }
}
