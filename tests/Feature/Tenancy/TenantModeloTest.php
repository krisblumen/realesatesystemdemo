<?php

namespace Tests\Feature\Tenancy;

use App\Enums\TenantEstado;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsaBaseCentral;
use Tests\TestCase;

/**
 * El padrón de inquilinos vive en la central y lo dice de forma explícita.
 *
 * `Tenant` declara su conexión en vez de heredar la por defecto, y eso no es
 * ceremonia: la conexión por defecto va a apuntar al inquilino de cada petición.
 * Un `Tenant` que la heredara funcionaría en el host central y fallaría en el
 * subdominio de un inquilino —o peor, leería una tabla `tenants` que no existe
 * ahí— justo cuando hace falta para resolver quién es.
 */
class TenantModeloTest extends TestCase
{
    use UsaBaseCentral;

    public function test_the_registry_declares_its_connection_instead_of_inheriting_it(): void
    {
        $this->assertSame('central', (new Tenant)->getConnectionName());
    }

    public function test_the_registry_answers_the_same_whatever_the_default_connection_is(): void
    {
        $inquilino = Tenant::create($this->datos());

        // Se mueve la conexión por defecto a una base que NO tiene la tabla
        // `tenants`, como pasará en cada petición de un inquilino.
        config(['database.connections.pgsql.database' => 'demo_sin_resolver']);
        DB::purge('pgsql');

        $this->assertTrue($inquilino->is(Tenant::query()->firstWhere('slug', $inquilino->slug)));
    }

    public function test_the_state_is_cast_to_the_enum(): void
    {
        $inquilino = Tenant::create($this->datos());

        $this->assertInstanceOf(TenantEstado::class, $inquilino->fresh()->estado);
        $this->assertSame(TenantEstado::Aprovisionando, $inquilino->fresh()->estado);
    }

    public function test_a_valid_transition_is_recorded(): void
    {
        $inquilino = Tenant::create($this->datos());

        $inquilino->pasarA(TenantEstado::Activo);

        $this->assertSame(TenantEstado::Activo, $inquilino->fresh()->estado);
    }

    public function test_an_invalid_transition_is_refused_and_changes_nothing(): void
    {
        // Un inquilino activo que salta a `borrado` se saltea la ventana entre
        // vencer y borrar, que existe para atender un reclamo antes de que sea
        // irreversible.
        $inquilino = Tenant::create($this->datos() + ['estado' => TenantEstado::Activo]);

        try {
            $inquilino->pasarA(TenantEstado::Borrado);
            $this->fail('Una transición inválida tiene que fallar ruidosamente.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('activo', $e->getMessage());
            $this->assertStringContainsString('borrado', $e->getMessage());
        }

        $this->assertSame(TenantEstado::Activo, $inquilino->fresh()->estado);
    }

    public function test_the_row_outlives_the_database_it_pointed_at(): void
    {
        // Se conserva a propósito: sirve para medir el uso del demo y para que
        // el mismo correo no recicle inquilinos sin dejar rastro.
        $inquilino = Tenant::create($this->datos() + ['estado' => TenantEstado::Expirado]);

        $inquilino->pasarA(TenantEstado::Borrado);

        $this->assertNotNull(Tenant::query()->firstWhere('slug', $inquilino->slug));
    }

    public function test_the_sweep_picks_up_a_failed_provisioning_not_only_an_expired_one(): void
    {
        // EL DEFECTO QUE ESTE TEST PREVIENE: si el barrido filtrara por «estado
        // terminal» o sólo por `expirado`, un alta que murió después de crear la
        // base dejaría esa base viva para siempre, ocupando conexiones y disco.
        Tenant::create($this->datos(['slug' => 'aaaa1111', 'estado' => TenantEstado::Fallido]));
        Tenant::create($this->datos(['slug' => 'bbbb2222', 'estado' => TenantEstado::Expirado]));
        Tenant::create($this->datos(['slug' => 'cccc3333', 'estado' => TenantEstado::Activo]));

        $barridos = Tenant::query()->paraBarrer()->pluck('slug')->all();

        sort($barridos);
        $this->assertSame(['aaaa1111', 'bbbb2222'], $barridos);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function datos(array $extra = []): array
    {
        $slug = $extra['slug'] ?? 'demo'.substr(md5(uniqid('', true)), 0, 4);

        // `database` se deriva del slug igual que en el alta real. Fijarlo a un
        // literal hacía que dos inquilinos del mismo test chocaran contra el
        // índice único, que es justo lo que ese índice tiene que impedir.
        return $extra + [
            'slug' => $slug,
            'database' => 'demo_t_'.$slug,
            'email' => 'prueba@ejemplo.com',
            'template_version' => 'v1',
            'expira_en' => now()->addDays(30),
        ];
    }
}
