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

    /**
     * Cuántas sesiones se abrieron contra esa base DESDE SIEMPRE.
     *
     * Es un contador acumulado, así que detecta también la conexión que se abre
     * y se cierra dentro de la misma operación — que es la que un chequeo de
     * sesiones vivas no ve.
     */
    private function sesionesAcumuladas(string $base): int
    {
        return (int) DB::connection('maintenance')->selectOne(
            'SELECT sessions AS n FROM pg_stat_database WHERE datname = ?',
            [$base],
        )->n;
    }

    public function test_the_operator_can_expire_a_tenant_today_without_waiting_for_its_date(): void
    {
        // La acción que el padrón necesita y que faltaba: alguien avisa que
        // cargó datos reales y pide cortar el demo hoy. Sin esto, atenderlo
        // termina siendo un UPDATE a mano en la base central.
        $pedido = $this->inquilino('cortaya', TenantEstado::Activo);
        $otro = $this->inquilino('siguevivo', TenantEstado::Activo);

        $this->artisan('demo:expirar', ['--slug' => $pedido->slug])->assertSuccessful();

        $this->assertSame(TenantEstado::Expirado, $pedido->fresh()->estado);
        $this->assertSame(TenantEstado::Activo, $otro->fresh()->estado, 'Sólo el pedido, no los demás.');
    }

    public function test_a_deletion_that_keeps_failing_shows_how_many_times(): void
    {
        // Con sólo el motivo, el operador no puede distinguir «falló recién una
        // vez» de «lleva tres noches fallando». El cron sigue reintentando en
        // silencio, que es justo lo que RFC-09 no quiere.
        $roto = $this->inquilino('nosepuede', TenantEstado::Expirado, '1 day');

        // Se le cambia la base por una que la última red rechaza: falla siempre,
        // de forma determinista.
        $roto->forceFill(['database' => config('database.connections.central.database')])->save();

        $this->artisan('demo:borrar')->assertFailed();
        $this->artisan('demo:borrar')->assertFailed();

        $this->assertSame(2, $roto->fresh()->intentos_de_borrado);

        $this->artisan('demo:padron')->expectsOutputToContain('2')->assertSuccessful();
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
        // por defecto es la central, y no abre la base de ningún inquilino.
        //
        // SE MIDE CON EL CONTADOR ACUMULADO DE SESIONES, no con las sesiones
        // vivas al final. Mirar `pg_stat_activity` después dejaba pasar una
        // conexión que se abre, lee y se cierra dentro del comando — que es
        // exactamente cómo alguien agregaría «cantidad de inmuebles» al padrón
        // sin darse cuenta de que está leyendo contenido interno. Lo destapó la
        // auditoría de implementación con esa mutación.
        $tenant = $this->inquilino('sinabrir', TenantEstado::Activo);

        $antes = $this->sesionesAcumuladas($tenant->database);

        $this->artisan('demo:padron')->assertSuccessful();

        $this->assertSame(
            $antes,
            $this->sesionesAcumuladas($tenant->database),
            'El padrón abrió una conexión contra la base de un inquilino, aunque la haya cerrado después.',
        );
    }
}
