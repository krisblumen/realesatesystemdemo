<?php

namespace App\Tenancy;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Hash;

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
        $password = (new GeneradorDeClave)->generar();

        $usuarioId = $conexion->table('users')->insertGetId([
            'name' => 'Owner',
            'email' => $email,
            'password' => Hash::make($password),
            'status' => UserStatus::Active->value,
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // DOS ROLES, Y NO ES UN DESCUIDO.
        //
        // `owner` para administrar, y `agente` para que la misma persona pueda
        // ver el otro lado del sistema —la vista por zona, los leads propios,
        // el panel recortado— sin tener que salir y entrar con otra cuenta.
        //
        // Quien viene a probar un demo lo hace con prisa: si para ver la mitad
        // del producto hay que descubrir que existe un segundo usuario, esa
        // mitad no se ve nunca.
        //
        // No se pisan: `Property::scopeVisibleTo` devuelve todo para `owner`,
        // así que sumar `agente` agrega pantallas sin quitar alcance.
        //
        // Los roles viajan en la plantilla (`PermissionSeeder`), así que acá
        // sólo se asignan. Si no existieran, esto falla ruidosamente — que es
        // lo correcto: un inquilino con un owner sin rol no administra nada.
        foreach (['owner', 'agente'] as $rol) {
            $rolId = $conexion->table('roles')->where('name', $rol)->value('id');

            $conexion->table('model_has_roles')->insert([
                'role_id' => $rolId,
                'model_type' => User::class,
                'model_id' => $usuarioId,
            ]);
        }

        // Un agente SIN ZONA ve una pantalla vacía, y entonces el rol no le
        // enseña nada. Se lo asigna a la primera zona activa, que en la
        // plantilla es el centro.
        $zonaId = $conexion->table('zones')->orderBy('id')->value('id');

        if ($zonaId !== null) {
            $conexion->table('agent_zone')->insert([
                'agent_id' => $usuarioId,
                'zone_id' => $zonaId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $password;
    }
}
