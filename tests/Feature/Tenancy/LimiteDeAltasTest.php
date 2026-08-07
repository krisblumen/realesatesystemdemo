<?php

namespace Tests\Feature\Tenancy;

use App\Enums\TenantEstado;
use App\Models\Tenant;
use App\Tenancy\LimiteAlcanzado;
use App\Tenancy\LimiteDeAltas;
use Tests\Concerns\UsaBaseCentral;
use Tests\TestCase;

/**
 * Los topes que hacen seguro abrir el demo (RFC-10).
 *
 * EL RIESGO QUE CIERRAN. El demo comparte instancia de Postgres con la
 * producción de New Hauz y con el correo: las 100 conexiones son de todos. Un
 * registro abierto sin tope puede dejar sin conexiones al sitio que factura.
 *
 * El número no se elige, se deriva (RFC-10): `simultáneos ≈ registros por día ×
 * días de vida`. Por eso vive en configuración y se puede bajar sin desplegar.
 */
class LimiteDeAltasTest extends TestCase
{
    use UsaBaseCentral;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'tenancy.limites.sal' => 'una-sal-de-prueba',
            'tenancy.limites.tope_ocupados' => 3,
            'tenancy.limites.por_origen' => 2,
            'tenancy.limites.ventana_horas' => 24,
        ]);
    }

    private function inquilino(TenantEstado $estado, ?string $origenHash = null, ?string $creado = null): Tenant
    {
        static $n = 0;
        $n++;

        $t = Tenant::create([
            'slug' => 'aaaa'.str_pad((string) $n, 8, 'b'),
            'database' => 'demo_probe_lim_'.$n,
            'email' => "lim{$n}@ejemplo.com",
            'template_version' => 'demo_template',
            'expira_en' => now()->addDays(30),
            'estado' => $estado,
            'origen_hash' => $origenHash,
        ]);

        if ($creado !== null) {
            $t->forceFill(['created_at' => $creado])->save();
        }

        return $t;
    }

    public function test_an_expired_tenant_still_counts_because_its_database_is_alive(): void
    {
        // LA DECISIÓN QUE EL RFC NO RESUELVE Y HAY QUE TOMAR.
        //
        // El recurso escaso no son los inquilinos «activos»: son las BASES VIVAS
        // en la instancia. Un inquilino expirado ya no atiende a nadie, pero su
        // base sigue ahí hasta que el barrido de las 3:30 la borre — ocupando
        // disco y conexiones exactamente igual.
        //
        // Contar sólo `activo` daría un cupo que no existe, y el síntoma sería
        // quedarse sin conexiones con el padrón diciendo que hay lugar.
        $this->inquilino(TenantEstado::Activo);
        $this->inquilino(TenantEstado::Expirado);
        $this->inquilino(TenantEstado::Aprovisionando);

        $this->expectException(LimiteAlcanzado::class);

        app(LimiteDeAltas::class)->verificar();
    }

    public function test_a_deleted_tenant_frees_its_place(): void
    {
        // `borrado` es el único estado sin base: el barrido ya pasó.
        $this->inquilino(TenantEstado::Borrado);
        $this->inquilino(TenantEstado::Borrado);
        $this->inquilino(TenantEstado::Borrado);
        $this->inquilino(TenantEstado::Activo);

        app(LimiteDeAltas::class)->verificar();

        $this->addToAssertionCount(1);
    }

    public function test_the_same_origin_cannot_register_again_and_again(): void
    {
        $limite = app(LimiteDeAltas::class);
        $hash = $limite->hashDe('203.0.113.7');

        $this->inquilino(TenantEstado::Borrado, $hash);
        $this->inquilino(TenantEstado::Borrado, $hash);

        $this->expectException(LimiteAlcanzado::class);

        $limite->verificar('203.0.113.7');
    }

    public function test_the_origin_limit_survives_deletion(): void
    {
        // Si contara sólo lo vivo, bastaría esperar a que expire para volver a
        // empezar. La fila del inquilino sobrevive al borrado (RFC-09) y es la
        // que se cuenta.
        $limite = app(LimiteDeAltas::class);
        $hash = $limite->hashDe('203.0.113.7');

        $this->inquilino(TenantEstado::Borrado, $hash);
        $this->inquilino(TenantEstado::Borrado, $hash);

        try {
            $limite->verificar('203.0.113.7');
            $this->fail('Un origen no puede reiniciar su cupo esperando el borrado.');
        } catch (LimiteAlcanzado) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_outside_the_window_the_origin_can_try_again(): void
    {
        $limite = app(LimiteDeAltas::class);
        $hash = $limite->hashDe('203.0.113.7');

        $this->inquilino(TenantEstado::Borrado, $hash, now()->subDays(2)->toDateTimeString());
        $this->inquilino(TenantEstado::Borrado, $hash, now()->subDays(2)->toDateTimeString());

        $limite->verificar('203.0.113.7');

        $this->addToAssertionCount(1);
    }

    public function test_the_origin_is_hashed_and_never_stored_in_the_clear(): void
    {
        $limite = app(LimiteDeAltas::class);

        $hash = $limite->hashDe('203.0.113.7');

        $this->assertNotSame('203.0.113.7', $hash);
        $this->assertSame(64, mb_strlen($hash), 'SHA-256 en hexadecimal.');

        // Con otra sal, otro hash: la sal es lo que impide reconstruir el origen
        // probando direcciones.
        config(['tenancy.limites.sal' => 'otra-sal']);

        $this->assertNotSame($hash, app(LimiteDeAltas::class)->hashDe('203.0.113.7'));
    }

    public function test_without_salt_it_refuses_instead_of_limiting_badly(): void
    {
        // «Si la sal rota, los límites se pierden en silencio, que es peor que no
        // tenerlos: nadie se entera» (RFC-10). Sin sal no se hashea con cadena
        // vacía: se niega. Es la misma regla del centinela — que falle fuerte y
        // no que funcione mal.
        config(['tenancy.limites.sal' => null]);

        $this->expectException(\RuntimeException::class);

        app(LimiteDeAltas::class)->hashDe('203.0.113.7');
    }

    public function test_the_message_says_when_to_try_again(): void
    {
        // Un visitante que quería probar el producto y recibe «algo salió mal» no
        // vuelve. El mensaje dice qué pasó y cuándo se puede reintentar.
        $limite = app(LimiteDeAltas::class);
        $hash = $limite->hashDe('203.0.113.7');

        $this->inquilino(TenantEstado::Activo, $hash);
        $this->inquilino(TenantEstado::Activo, $hash);

        try {
            $limite->verificar('203.0.113.7');
            $this->fail('Tendría que haber cortado.');
        } catch (LimiteAlcanzado $e) {
            $this->assertNotNull($e->reintentarDesde());
            $this->assertNotSame('', trim($e->getMessage()));
        }
    }

    public function test_without_an_origin_only_the_hard_cap_applies(): void
    {
        // La invitación por consola no tiene origen: la limita el operador. Pero
        // el tope duro protege la INSTANCIA, así que vale para todos los caminos
        // — es lo único que separa al demo de tumbar el sitio que factura.
        $limite = app(LimiteDeAltas::class);
        $hash = $limite->hashDe('203.0.113.7');

        $this->inquilino(TenantEstado::Borrado, $hash);
        $this->inquilino(TenantEstado::Borrado, $hash);

        $limite->verificar();

        $this->addToAssertionCount(1);
    }
}
