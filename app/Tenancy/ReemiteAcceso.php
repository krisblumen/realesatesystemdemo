<?php

namespace App\Tenancy;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Le devuelve el acceso al `owner` de un inquilino que ya existe.
 *
 * Es el espejo de `CreadorDeOwner`, y colaborador por el mismo motivo: la parte
 * que toca la base del inquilino se puede probar sin levantar el comando entero
 * ni construir una plantilla.
 *
 * NO CREA NADA. Si el usuario no está, lo dice y no lo inventa: un inquilino sin
 * owner es un alta que se cayó a la mitad, y taparlo con un usuario nuevo
 * escondería el problema en vez de mostrarlo.
 */
class ReemiteAcceso
{
    /**
     * @return string|null La nueva contraseña en claro, o null si no hay usuario
     *                     con ese correo en la base del inquilino.
     */
    public function para(Connection $conexion, string $email): ?string
    {
        $usuarioId = $conexion->table('users')->where('email', $email)->value('id');

        if ($usuarioId === null) {
            return null;
        }

        $password = Str::password(16);

        $conexion->table('users')->where('id', $usuarioId)->update([
            'password' => Hash::make($password),
            // Se limpia el «recordarme»: si se reemite el acceso es porque se
            // perdió, y una sesión recordada en un navegador ajeno sobreviviría
            // al cambio de contraseña.
            'remember_token' => null,
            'updated_at' => now(),
        ]);

        return $password;
    }
}
