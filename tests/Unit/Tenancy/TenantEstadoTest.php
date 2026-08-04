<?php

namespace Tests\Unit\Tenancy;

use App\Enums\TenantEstado;
use PHPUnit\Framework\TestCase;

/**
 * La máquina de estados de un inquilino.
 *
 * Existe como enum con un único método de transición porque un `estado`
 * asignado a mano en cualquier otro lugar es un error de implementación, no una
 * alternativa: sin un punto único, cada camino de alta y de borrado inventa su
 * propia secuencia y nadie puede decir cuáles son válidas.
 */
class TenantEstadoTest extends TestCase
{
    public function test_the_valid_path_of_a_tenants_life_is_allowed(): void
    {
        $this->assertTrue(TenantEstado::Aprovisionando->puedePasarA(TenantEstado::Activo));
        $this->assertTrue(TenantEstado::Aprovisionando->puedePasarA(TenantEstado::Fallido));
        $this->assertTrue(TenantEstado::Activo->puedePasarA(TenantEstado::Expirado));
        $this->assertTrue(TenantEstado::Expirado->puedePasarA(TenantEstado::Borrado));
    }

    public function test_going_backwards_is_refused(): void
    {
        // Un inquilino borrado que vuelve a `activo` sería un inquilino sin base.
        $this->assertFalse(TenantEstado::Borrado->puedePasarA(TenantEstado::Activo));
        $this->assertFalse(TenantEstado::Expirado->puedePasarA(TenantEstado::Activo));
        $this->assertFalse(TenantEstado::Activo->puedePasarA(TenantEstado::Aprovisionando));
    }

    public function test_skipping_expiry_is_refused(): void
    {
        // Borrar sin pasar por `expirado` saltea la ventana que existe a
        // propósito para atender un reclamo antes de que sea irreversible.
        $this->assertFalse(TenantEstado::Activo->puedePasarA(TenantEstado::Borrado));
    }

    public function test_a_failed_provisioning_is_swept_even_though_it_is_terminal(): void
    {
        // EL PUNTO DE ESTE TEST. `fallido` es terminal para el ciclo de vida
        // —no transiciona— pero SÍ entra al barrido de limpieza: si el alta
        // murió después de CREATE DATABASE, hay una base viva sin dueño. Una
        // limpieza que filtre sólo `expirado` la deja ahí ocupando conexiones y
        // disco, y el padrón la muestra como si no existiera.
        $this->assertFalse(TenantEstado::Fallido->puedePasarA(TenantEstado::Activo));
        $this->assertFalse(TenantEstado::Fallido->puedePasarA(TenantEstado::Borrado));

        $this->assertTrue(TenantEstado::Fallido->requiereBarridoDeBase());
        $this->assertTrue(TenantEstado::Expirado->requiereBarridoDeBase());
    }

    public function test_states_that_never_left_a_database_behind_are_not_swept(): void
    {
        // Barrer un estado que nunca creó base es trabajo inútil, y peor: haría
        // que el barrido tenga que tolerar «no existe» como caso normal, con lo
        // que dejaría de distinguirlo de un borrado que falló.
        $this->assertFalse(TenantEstado::Aprovisionando->requiereBarridoDeBase());
        $this->assertFalse(TenantEstado::Activo->requiereBarridoDeBase());
        $this->assertFalse(TenantEstado::Borrado->requiereBarridoDeBase());
    }

    public function test_only_an_active_tenant_resolves_a_request(): void
    {
        $this->assertTrue(TenantEstado::Activo->resuelvePeticiones());

        foreach ([TenantEstado::Aprovisionando, TenantEstado::Fallido, TenantEstado::Expirado, TenantEstado::Borrado] as $estado) {
            $this->assertFalse($estado->resuelvePeticiones(), "{$estado->value} no debe resolver peticiones.");
        }
    }
}
