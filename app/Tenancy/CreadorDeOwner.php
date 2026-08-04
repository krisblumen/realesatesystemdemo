<?php

namespace App\Tenancy;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Crea el usuario `owner` DENTRO de la base del inquilino.
 *
 * Es un colaborador propio y no un método privado del aprovisionador por una
 * razón concreta: es el primer paso del alta que puede fallar cuando YA existe
 * una base, y hay que poder probar qué queda cuando eso pasa. Con una clase
 * aparte, el test intercambia esta pieza; con un método privado, habría que
 * meter una costura de prueba en el código de producción.
 */
class CreadorDeOwner
{
    /**
     * @return string La contraseña generada, en claro y por única vez.
     */
    public function crear(Connection $conexion, string $email): string
    {
        $password = Str::password(16);

        $usuarioId = $conexion->table('users')->insertGetId([
            'name' => 'Owner',
            'email' => $email,
            'password' => Hash::make($password),
            'status' => UserStatus::Active->value,
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // El rol viaja en la plantilla (`PermissionSeeder`), así que acá sólo se
        // asigna. Si no existiera, esto falla ruidosamente — que es lo correcto:
        // un inquilino con un owner sin rol no puede administrar nada.
        $rolId = $conexion->table('roles')->where('name', 'owner')->value('id');

        $conexion->table('model_has_roles')->insert([
            'role_id' => $rolId,
            'model_type' => User::class,
            'model_id' => $usuarioId,
        ]);

        return $password;
    }
}
