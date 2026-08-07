<?php

namespace Tests\Feature\Tenancy;

use App\Enums\TenantEstado;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsaBaseCentral;
use Tests\TestCase;

/**
 * `demo:invitar`: la puerta de entrada del demo en fase 1.
 *
 * Sale por consola y no por correo, y eso no es un atajo: quita el correo como
 * punto de falla. Un mensaje que cae en spam es una persona que quería probar el
 * producto y no pudo, con un inquilino aprovisionado ocupando lugar.
 */
class InvitacionTest extends TestCase
{
    use UsaBaseCentral;

    private const PLANTILLA = 'demo_probe_inv_tpl';

    private static bool $plantillaLista = false;

    private array $creadas = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (! self::$plantillaLista) {
            $this->artisan('demo:plantilla:construir', ['nombre' => self::PLANTILLA, '--force' => true])
                ->assertSuccessful();
            self::$plantillaLista = true;
        }

        config(['tenancy.plantilla_vigente' => self::PLANTILLA]);
    }

    protected function tearDown(): void
    {
        foreach (Tenant::query()->pluck('database') as $base) {
            DB::connection('maintenance')->statement('DROP DATABASE IF EXISTS "'.$base.'"');
        }

        parent::tearDown();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$plantillaLista) {
            $pdo = new \PDO(
                'pgsql:host='.env('DB_HOST', '127.0.0.1').';port='.env('DB_PORT', '5432').';dbname=postgres',
                env('DB_USERNAME', 'postgres'),
                (string) env('DB_PASSWORD', ''),
            );
            $pdo->exec('DROP DATABASE IF EXISTS "'.self::PLANTILLA.'"');
            self::$plantillaLista = false;
        }

        parent::tearDownAfterClass();
    }

    public function test_the_invitation_hands_over_everything_needed_to_get_in(): void
    {
        $this->artisan('demo:invitar', ['email' => 'invitado@ejemplo.com'])
            ->assertSuccessful();

        $tenant = Tenant::query()->firstWhere('email', 'invitado@ejemplo.com');

        $this->assertNotNull($tenant);
        $this->assertSame(TenantEstado::Activo, $tenant->estado);
    }

    public function test_the_hard_cap_stops_the_invitation_too(): void
    {
        // El tope duro protege la INSTANCIA —las 100 conexiones compartidas con
        // la producción de New Hauz y el correo— y eso no depende de por dónde
        // entró el alta. Si sólo frenara el registro público, el camino de
        // invitación sería una puerta abierta al mismo riesgo.
        config(['tenancy.limites.tope_ocupados' => 1]);

        $this->artisan('demo:invitar', ['email' => 'primero@ejemplo.com'])
            ->assertSuccessful();

        $this->artisan('demo:invitar', ['email' => 'segundo@ejemplo.com'])
            ->assertFailed();

        $this->assertNull(
            Tenant::query()->firstWhere('email', 'segundo@ejemplo.com'),
            'Un alta frenada por el tope no deja fila en el padrón.',
        );
    }

    public function test_the_invitation_says_which_template_it_used(): void
    {
        // EL DEFECTO QUE ESTE TEST PREVIENE, y pasó en producción.
        //
        // Se construyó una plantilla nueva, se apuntó el `.env` sin limpiar la
        // caché de configuración, y el alta siguió usando la anterior. El
        // comando dijo «inquilino listo» y el error se descubrió recién al abrir
        // el panel y ver contenido viejo.
        //
        // El dato ya existía —el padrón lo guarda— pero llegaba tarde: después
        // de haber invitado a alguien. Un dato que sólo sirve para la autopsia
        // no es un dato, es un consuelo.
        $this->artisan('demo:invitar', ['email' => 'invitado@ejemplo.com'])
            ->expectsOutputToContain(self::PLANTILLA)
            ->assertSuccessful();
    }

    public function test_the_invitation_warns_about_what_can_be_uploaded(): void
    {
        // El límite aceptado en RFC-14: la media publicada se sirve por el
        // servidor web sin pasar por la sesión. Un límite conocido que no llega
        // a quien va a subir archivos no es un límite aceptado, es un descuido
        // con papeles.
        $this->artisan('demo:invitar', ['email' => 'aviso@ejemplo.com'])
            ->expectsOutputToContain('público')
            ->assertSuccessful();
    }

    public function test_inviting_the_same_active_email_twice_is_refused(): void
    {
        $this->artisan('demo:invitar', ['email' => 'repetido@ejemplo.com'])->assertSuccessful();

        $this->artisan('demo:invitar', ['email' => 'repetido@ejemplo.com'])
            ->assertFailed();

        $this->assertSame(1, Tenant::query()->where('email', 'repetido@ejemplo.com')->count());
    }

    public function test_the_lifetime_is_fixed_at_creation_and_can_be_chosen(): void
    {
        // Un inquilino sin fecha de vencimiento es un inquilino eterno, y de
        // esos se juntan. Se fija en el alta, no se calcula al leer.
        $this->artisan('demo:invitar', ['email' => 'corto@ejemplo.com', '--dias' => 3])
            ->assertSuccessful();

        $tenant = Tenant::query()->firstWhere('email', 'corto@ejemplo.com');

        $this->assertEqualsWithDelta(3, now()->diffInDays($tenant->expira_en, false), 1);
    }

    public function test_a_refused_invitation_creates_nothing(): void
    {
        $this->artisan('demo:invitar', ['email' => 'no-es-un-correo'])->assertFailed();

        $this->assertSame(0, Tenant::query()->count());
    }
}
