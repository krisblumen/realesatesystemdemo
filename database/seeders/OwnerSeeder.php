<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OwnerSeeder extends Seeder
{
    public const DEFAULT_EMAIL = 'owner@newhauz.test';

    public function run(): void
    {
        $email = env('OWNER_EMAIL') ?: self::DEFAULT_EMAIL;
        $password = env('OWNER_PASSWORD') ?: Str::password(16);

        $owner = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Owner',
                'password' => Hash::make($password),
                'status' => UserStatus::Active,
            ],
        );

        if (! $owner->hasRole('owner')) {
            $owner->assignRole('owner');
        }

        if ($owner->wasRecentlyCreated) {
            $this->command?->info("Owner creado: {$email}");

            if (! env('OWNER_PASSWORD')) {
                $this->command?->warn("Contraseña generada: {$password}");
                $this->command?->warn('Guardala ahora: no se vuelve a mostrar.');
            }

            return;
        }

        $this->command?->info("Owner ya existía: {$email} (sin cambios).");
    }
}
