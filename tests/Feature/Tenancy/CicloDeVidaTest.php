<?php

namespace Tests\Feature\Tenancy;

use App\Enums\TenantEstado;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsaBaseCentral;
use Tests\TestCase;

/**
 * Marcar vencido y borrar son DOS COSAS SEPARADAS, y a propósito.
 *
 * Marcar es barato, inmediato y confiable; borrar es caro, irreversible y puede
 * fallar. Separarlas hace que el corte de acceso no dependa de que el borrado
 * funcione, y deja en el medio una ventana en la que el inquilino ya no entra
 * pero sus datos existen — margen para atender un reclamo antes de que sea
 * imposible.
 */
class CicloDeVidaTest extends TestCase
{
    use UsaBaseCentral;

    private array $creadas = [];

    protected function tearDown(): void
    {
        foreach ($this->creadas as $base) {
            $existe = DB::connection('maintenance')
                ->selectOne('SELECT 1 AS x FROM pg_database WHERE datname = ?', [$base]);

            if ($existe !== null) {
                DB::connection('maintenance')->statement('ALTER DATABASE "'.$base.'" CONNECTION LIMIT -1');
                DB::connection('maintenance')->statement('DROP DATABASE IF EXISTS "'.$base.'"');
            }
        }

        parent::tearDown();
    }

    private function inquilino(string $slug, TenantEstado $estado, ?string $vence = null): Tenant
    {
        $base = config('tenancy.prefijo_pruebas').$slug;

        DB::connection('maintenance')->statement('DROP DATABASE IF EXISTS "'.$base.'"');
        DB::connection('maintenance')->statement('CREATE DATABASE "'.$base.'"');
        $this->creadas[] = $base;

        return Tenant::create([
            'slug' => $slug.'zzzz',
            'database' => $base,
            'email' => $slug.'@ejemplo.com',
            'template_version' => 'demo_template',
            'expira_en' => $vence === null ? now()->addDays(10) : now()->sub($vence),
            'estado' => $estado,
        ]);
    }

    public function test_only_tenants_past_their_date_are_marked_expired(): void
    {
        $vencido = $this->inquilino('vencido', TenantEstado::Activo, '1 day');
        $vigente = $this->inquilino('vigente', TenantEstado::Activo);

        $this->artisan('demo:expirar')->assertSuccessful();

        $this->assertSame(TenantEstado::Expirado, $vencido->fresh()->estado);
        $this->assertSame(TenantEstado::Activo, $vigente->fresh()->estado);
    }

    public function test_marking_expired_does_not_delete_anything(): void
    {
        // La ventana entre vencer y borrar es deliberada: da margen para
        // atender un reclamo antes de que sea irreversible.
        $tenant = $this->inquilino('conventana', TenantEstado::Activo, '1 day');

        $this->artisan('demo:expirar')->assertSuccessful();

        $existe = DB::connection('maintenance')
            ->selectOne('SELECT 1 AS x FROM pg_database WHERE datname = ?', [$tenant->database]);

        $this->assertNotNull($existe, 'Marcar vencido no puede borrar la base.');
    }

    public function test_the_sweep_picks_up_a_failed_provisioning_too(): void
    {
        // EL DEFECTO QUE ESTE TEST PREVIENE: un barrido que filtrara sólo
        // `expirado` dejaría vivas para siempre las bases de altas que murieron
        // después del CREATE DATABASE — ocupando conexiones y disco, con el
        // padrón mostrándolas como si no existieran.
        $expirado = $this->inquilino('expirado', TenantEstado::Expirado, '1 day');
        $fallido = $this->inquilino('fallido', TenantEstado::Fallido, '1 day');
        $activo = $this->inquilino('activo', TenantEstado::Activo);

        $this->artisan('demo:borrar')->assertSuccessful();

        $this->assertSame(TenantEstado::Borrado, $expirado->fresh()->estado);
        $this->assertSame(TenantEstado::Fallido, $fallido->fresh()->estado, 'Un alta fallida no transiciona…');

        $existeFallido = DB::connection('maintenance')
            ->selectOne('SELECT 1 AS x FROM pg_database WHERE datname = ?', [$fallido->database]);
        $this->assertNull($existeFallido, '…pero SU BASE sí se barre.');

        $existeActivo = DB::connection('maintenance')
            ->selectOne('SELECT 1 AS x FROM pg_database WHERE datname = ?', [$activo->database]);
        $this->assertNotNull($existeActivo, 'Un inquilino activo no se toca.');
    }

    public function test_the_registry_shows_what_happened_without_opening_anyones_content(): void
    {
        $this->inquilino('padronuno', TenantEstado::Activo);
        $roto = $this->inquilino('padrondos', TenantEstado::Fallido, '1 day');
        $roto->forceFill(['motivo_falla' => 'la plantilla no tenía inmuebles'])->save();

        $this->artisan('demo:padron')
            ->expectsOutputToContain('padronunozzzz')
            ->expectsOutputToContain('la plantilla no tenía inmuebles')
            ->assertSuccessful();
    }

    public function test_the_registry_never_connects_to_a_tenant_database(): void
    {
        // La garantía de RFC-12 es estructural: el padrón vive donde la conexión
        // por defecto es la central, y no hay ninguna abierta contra la base de
        // un inquilino desde donde leer algo de adentro.
        $tenant = $this->inquilino('sinabrir', TenantEstado::Activo);

        $this->artisan('demo:padron')->assertSuccessful();

        $sesiones = DB::connection('maintenance')->selectOne(
            'SELECT count(*) AS n FROM pg_stat_activity WHERE datname = ?',
            [$tenant->database],
        );

        $this->assertSame(0, (int) $sesiones->n, 'El padrón no abre la base de nadie.');
    }
}
