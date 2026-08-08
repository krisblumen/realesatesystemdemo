<?php

namespace Tests\Feature\Tenancy;

use App\Enums\TenantEstado;
use App\Jobs\AprovisionaUnInquilino;
use App\Models\Tenant;
use App\Notifications\AltaDeDemoEntregada;
use App\Notifications\InvitacionAlDemo;
use App\Tenancy\LimiteDeAltas;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
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

    private const OPERADOR = 'operador@ejemplo.com';

    private static bool $plantillaLista = false;

    private array $creadas = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['tenancy.limites.sal' => 'una-sal-de-prueba']);

        if (! self::$plantillaLista) {
            $this->artisan('demo:plantilla:construir', ['nombre' => self::PLANTILLA, '--force' => true])
                ->assertSuccessful();
            self::$plantillaLista = true;
        }

        config(['tenancy.plantilla_vigente' => self::PLANTILLA]);
        config(['tenancy.aviso_de_altas' => self::OPERADOR]);

        Notification::fake();
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

    private function invitar(string $email = 'invitado@ejemplo.com'): void
    {
        $this->artisan('demo:invitar', ['email' => $email])->assertSuccessful();
    }

    public function test_the_guest_receives_everything_needed_to_get_in(): void
    {
        $this->invitar();

        Notification::assertSentTo(
            new AnonymousNotifiable,
            InvitacionAlDemo::class,
            function (InvitacionAlDemo $aviso, array $canales, AnonymousNotifiable $a): bool {
                $correo = $aviso->toMail($a)->render();

                // Se compara sobre el texto VISIBLE y no sobre el HTML: la
                // contraseña va en un bloque de código, así que `<` y `&`
                // aparecen como entidades. Lo que importa es lo que se lee.
                $visible = html_entity_decode(strip_tags($correo), ENT_QUOTES | ENT_HTML5, 'UTF-8');

                return $a->routes['mail'] === 'invitado@ejemplo.com'
                    && str_contains($visible, $aviso->password)
                    && str_contains($visible, 'invitado@ejemplo.com');
            },
        );
    }

    public function test_the_operator_gets_the_receipt_without_the_password(): void
    {
        // LO QUE ESTE TEST CUIDA. La contraseña en la bandeja del operador no
        // aporta nada —tiene el padrón y puede reemitir— y queda ahí para
        // siempre, protegiendo datos reales que el invitado va a cargar.
        $this->invitar();

        Notification::assertSentTo(
            new AnonymousNotifiable,
            AltaDeDemoEntregada::class,
            function (AltaDeDemoEntregada $aviso, array $canales, AnonymousNotifiable $a): bool {
                $correo = $aviso->toMail($a)->render();
                $password = Tenant::query()->firstWhere('email', 'invitado@ejemplo.com')?->slug;

                return $a->routes['mail'] === self::OPERADOR
                    && str_contains($correo, 'invitado@ejemplo.com')
                    && ! str_contains($correo, 'Contraseña');
            },
        );
    }

    public function test_without_an_operator_address_only_the_guest_is_notified(): void
    {
        config(['tenancy.aviso_de_altas' => null]);

        $this->invitar();

        // Se pregunta por la NOTIFICACIÓN y no por el destinatario:
        // `AnonymousNotifiable` no distingue rutas, así que aserciones por
        // destinatario darían igual para los dos correos.
        Notification::assertSentTo(new AnonymousNotifiable, InvitacionAlDemo::class);
        Notification::assertNotSentTo(new AnonymousNotifiable, AltaDeDemoEntregada::class);
    }

    public function test_a_failing_mail_does_not_lose_the_alta_nor_the_access(): void
    {
        // EL CASO QUE IMPORTA (RFC-11). El correo es el eslabón que no controlamos:
        // cae en spam, se demora, rebota. Si su fallo tumbara el alta, cada
        // problema de correo dejaría un inquilino aprovisionado y a nadie
        // adentro.
        //
        // El acceso se muestra EN PANTALLA de todos modos, así que quien invitó
        // puede entregarlo a mano. El correo va además, no en lugar de.
        Notification::fake();

        $this->mock(Dispatcher::class, function ($mock): void {
            $mock->shouldReceive('send')->andThrow(new \RuntimeException('smtp caído'));
            $mock->shouldReceive('sendNow')->andThrow(new \RuntimeException('smtp caído'));
        });

        $this->artisan('demo:invitar', ['email' => 'invitado@ejemplo.com'])
            ->expectsOutputToContain('invitado@ejemplo.com')
            ->assertSuccessful();

        $this->assertNotNull(
            Tenant::query()->firstWhere('email', 'invitado@ejemplo.com'),
            'El inquilino se creó igual: el correo no manda sobre el alta.',
        );
    }

    public function test_the_job_run_on_its_own_leaves_a_tenant_that_works(): void
    {
        // EL TRABAJO CORRIDO SOLO, que es como va a correr desde el registro
        // público: sin comando, sin nadie mirando, adentro del worker.
        //
        // Es lo único que prueba que la contraseña que devuelve sirve de verdad.
        // Un alta que crea la base pero deja al owner sin poder entrar termina
        // igual: alguien esperando un correo con un acceso que no abre nada.
        $hash = app(LimiteDeAltas::class)->hashDe('203.0.113.7');

        $trabajo = new AprovisionaUnInquilino('invitado@ejemplo.com', $hash);

        // Se resuelve por el contenedor, igual que lo hace la cola: así el test
        // no queda atado a la firma de `handle()`.
        $resultado = app()->call([$trabajo, 'handle']);

        $this->assertSame(TenantEstado::Activo, $resultado->tenant->estado);

        // EL ORIGEN TIENE QUE LLEGAR HASTA LA FILA, y no basta con que el
        // trabajo lo lleve en una propiedad: el límite por origen (RFC-10)
        // cuenta filas del padrón. Si el trabajo lo recibiera y no lo pasara al
        // alta, el registro público quedaría sin freno por origen y nada avisaría.
        $this->assertSame($hash, $resultado->tenant->fresh()->origen_hash);

        // Conexión propia y efímera, nunca reapuntando la por defecto: el mismo
        // cuidado de `AltaDeInquilinoTest`, y por el mismo motivo.
        config(['database.connections.probe_alta' => array_merge(
            config('database.connections.pgsql'),
            ['database' => $resultado->tenant->database],
        )]);
        DB::purge('probe_alta');

        try {
            $usuario = DB::connection('probe_alta')
                ->table('users')->where('email', 'invitado@ejemplo.com')->first();
        } finally {
            DB::purge('probe_alta');
        }

        $this->assertNotNull($usuario, 'El inquilino nace con su owner adentro.');
        $this->assertTrue(
            Hash::check($resultado->password, $usuario->password),
            'La contraseña que devuelve el trabajo tiene que ser con la que se entra.',
        );
    }

    public function test_the_invitation_runs_it_now_and_shows_the_access(): void
    {
        // La invitación NO encola: la corre en el momento. Quien está en la
        // terminal se entera ahí si funcionó.
        Queue::fake();

        $this->artisan('demo:invitar', ['email' => 'invitado@ejemplo.com'])
            ->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_a_tenant_created_without_origin_leaves_the_column_empty(): void
    {
        // La invitación por consola no tiene origen: a quien invita lo limita
        // ser el operador. Un hash inventado ahí ensuciaría el conteo del
        // registro público.
        $this->artisan('demo:invitar', ['email' => 'invitado@ejemplo.com'])
            ->assertSuccessful();

        $this->assertNull(
            Tenant::query()->firstWhere('email', 'invitado@ejemplo.com')?->origen_hash,
        );
    }

    public function test_the_queued_path_delivers_the_access_by_mail(): void
    {
        // LO QUE FALTABA PARA QUE EL REGISTRO PÚBLICO SIRVA DE ALGO.
        //
        // Los correos vivían en `demo:invitar`. Un alta encolada creaba la base,
        // el owner y la contraseña — y no avisaba a nadie. La contraseña sólo
        // existe en memoria: el inquilino quedaba con dueño que no puede entrar.
        //
        // Se corre el trabajo SOLO, sin comando, que es como corre en el worker.
        Notification::fake();

        $trabajo = new AprovisionaUnInquilino('invitado@ejemplo.com');

        app()->call([$trabajo, 'handle']);

        Notification::assertSentOnDemand(
            InvitacionAlDemo::class,
            fn ($n, $canales, AnonymousNotifiable $a) => $a->routes['mail'] === 'invitado@ejemplo.com',
        );
    }

    public function test_a_queued_alta_survives_a_mail_that_does_not_go_out(): void
    {
        // El correo es el eslabón que no controlamos, y acá NADIE está mirando:
        // si su fallo tumbara el trabajo, el alta se marcaría fallida y la base
        // recién creada se borraría — por un problema de SMTP.
        //
        // El fallo viaja como dato. El inquilino queda activo y se puede reemitir
        // el acceso con `demo:reemitir-acceso`.
        $this->mock(Dispatcher::class, function ($mock): void {
            $mock->shouldReceive('send')->andThrow(new \RuntimeException('smtp caído'));
            $mock->shouldReceive('sendNow')->andThrow(new \RuntimeException('smtp caído'));
        });

        $resultado = app()->call([new AprovisionaUnInquilino('invitado@ejemplo.com'), 'handle']);

        $this->assertSame(TenantEstado::Activo, $resultado->tenant->estado);
        $this->assertNotNull($resultado->falloDeCorreo, 'El fallo tiene que llegar a quien despachó, no perderse.');
    }
}
