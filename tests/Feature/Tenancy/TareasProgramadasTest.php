<?php

namespace Tests\Feature\Tenancy;

use App\Enums\TenantEstado;
use App\Models\Tenant;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Process;
use Tests\Concerns\UsaBaseCentral;
use Tests\TestCase;

/**
 * Las tareas programadas, en un sistema donde cada inquilino tiene su base.
 *
 * Un comando de consola NO TIENE SUBDOMINIO del cual resolver un inquilino, así
 * que su conexión por defecto se queda en el centinela. Eso parte las tareas en
 * dos familias que no se pueden agendar igual:
 *
 * - Las CENTRALES (`demo:expirar`, `demo:borrar`) leen el padrón, que vive en la
 *   central por conexión declarada. Se agendan directo.
 * - Las de INQUILINO (leads, media, contratos) tocan datos que viven en la base
 *   de cada uno. Agendadas directo apuntan al centinela y mueren. Tienen que
 *   correr UNA VEZ POR INQUILINO, cada una en su base.
 *
 * Se heredaron agendadas de forma global de la plataforma de origen, donde había
 * una sola base y la distinción no existía.
 */
class TareasProgramadasTest extends TestCase
{
    use UsaBaseCentral;

    /**
     * Comandos que tocan datos de un inquilino y por lo tanto no pueden correr
     * una sola vez para todos.
     *
     * @var array<int, string>
     */
    private const DE_INQUILINO = [
        'leads:reconcile',
        'frontend:media:reconcile',
        'contratos:expirar',
        'contratos:vencer',
        'contratos:retencion',
    ];

    private function evento(string $contiene): ?object
    {
        foreach (app(Schedule::class)->events() as $evento) {
            if (str_contains((string) $evento->command, $contiene)) {
                return $evento;
            }
        }

        return null;
    }

    public function test_the_scheduler_locks_live_in_the_central_database(): void
    {
        // `withoutOverlapping()` guarda su cerrojo en el CACHÉ, y el caché usa la
        // conexión por defecto — que desde consola es el centinela.
        //
        // Sin un almacén declarado, `demo:borrar` falla antes de empezar: el
        // comando que borra bases vencidas no llega a mirar ni una. Y no se nota
        // en desarrollo, donde la conexión por defecto apunta a una base que sí
        // existe.
        $evento = $this->evento('demo:borrar');

        $this->assertNotNull($evento, 'El barrido de inquilinos tiene que estar agendado.');
        $this->assertSame(
            'central',
            $evento->mutex->store,
            'Los cerrojos del programador tienen que vivir en la central, no en la conexión por defecto.',
        );
    }

    public function test_no_tenant_scoped_task_is_scheduled_globally(): void
    {
        // EL DEFECTO QUE ESTE TEST PREVIENE.
        //
        // Agendadas de forma global corren contra el centinela y mueren. El
        // síntoma es ruidoso —fallan cada pocos minutos— pero lo que importa es
        // silencioso: el trabajo del inquilino NUNCA OCURRE.
        foreach (self::DE_INQUILINO as $comando) {
            $evento = $this->evento($comando);

            $this->assertNotNull($evento, "«{$comando}» tiene que seguir agendado.");
            $this->assertStringContainsString(
                'demo:por-cada-inquilino',
                (string) $evento->command,
                "«{$comando}» toca datos de un inquilino: no puede correr una sola vez para todos.",
            );
        }
    }

    public function test_the_runner_visits_every_active_tenant_and_only_those(): void
    {
        Process::fake();

        $this->inquilino('aaaabbbbcccc', 'demo_probe_tar_a');
        $this->inquilino('ddddeeeeffff', 'demo_probe_tar_b');
        $this->inquilino('gggghhhhiiii', 'demo_probe_tar_c', TenantEstado::Expirado);
        $this->inquilino('jjjjkkkkllll', 'demo_probe_tar_d', TenantEstado::Fallido);

        $this->artisan('demo:por-cada-inquilino', ['comando' => 'leads:reconcile'])
            ->assertSuccessful();

        // Un inquilino expirado ya no atiende a nadie y uno fallido puede no
        // tener base: correrles tareas es trabajo perdido en el mejor caso.
        Process::assertRanTimes(fn ($proceso): bool => in_array('leads:reconcile', (array) $proceso->command, true), 2);
    }

    public function test_each_tenant_gets_its_own_database(): void
    {
        // Es TODO el punto del comando. Si el hijo no recibe la base, corre
        // contra el centinela igual que si no existiera este recorrido.
        Process::fake();

        $this->inquilino('aaaabbbbcccc', 'demo_probe_tar_a');
        $this->inquilino('ddddeeeeffff', 'demo_probe_tar_b');

        $this->artisan('demo:por-cada-inquilino', ['comando' => 'leads:reconcile']);

        foreach (['demo_probe_tar_a', 'demo_probe_tar_b'] as $base) {
            Process::assertRan(
                fn ($proceso): bool => ($proceso->environment['DB_DATABASE'] ?? null) === $base,
            );
        }
    }

    public function test_one_tenant_failing_does_not_stop_the_rest(): void
    {
        // Con veinte inquilinos, que el número tres tumbe a los diecisiete de
        // atrás convierte un fallo puntual en un apagón. Se reporta y se sigue.
        Process::fake([
            '*' => Process::sequence()
                ->push(Process::result(output: '', errorOutput: 'se rompió', exitCode: 1))
                ->push(Process::result(output: 'ok')),
        ]);

        $this->inquilino('aaaabbbbcccc', 'demo_probe_tar_a');
        $this->inquilino('ddddeeeeffff', 'demo_probe_tar_b');

        $this->artisan('demo:por-cada-inquilino', ['comando' => 'leads:reconcile'])
            ->assertFailed();

        Process::assertRanTimes(fn ($proceso): bool => in_array('leads:reconcile', (array) $proceso->command, true), 2);
    }

    public function test_it_refuses_to_run_a_destructive_command_on_every_tenant(): void
    {
        // Un recorrido por TODOS los inquilinos es exactamente el peor lugar para
        // que se cuele un comando destructivo: multiplica el daño por la
        // cantidad de gente que confió en el demo.
        Process::fake();

        $this->inquilino('aaaabbbbcccc', 'demo_probe_tar_a');

        foreach (['migrate:fresh', 'migrate:refresh', 'migrate:reset', 'db:wipe'] as $comando) {
            $this->artisan('demo:por-cada-inquilino', ['comando' => $comando])
                ->assertFailed();
        }

        Process::assertNothingRan();
    }

    private function inquilino(string $slug, string $base, TenantEstado $estado = TenantEstado::Activo): Tenant
    {
        return Tenant::create([
            'slug' => $slug,
            'database' => $base,
            'email' => $slug.'@ejemplo.com',
            'template_version' => 'demo_template',
            'expira_en' => now()->addDays(30),
            'estado' => $estado,
        ]);
    }
}
