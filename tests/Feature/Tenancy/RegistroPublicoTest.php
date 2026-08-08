<?php

namespace Tests\Feature\Tenancy;

use App\Enums\TenantEstado;
use App\Jobs\AprovisionaUnInquilino;
use App\Models\Tenant;
use App\Tenancy\LimiteDeAltas;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\UsaBaseCentral;
use Tests\TestCase;

/**
 * El registro público de un demo, en `/guest` del host central.
 *
 * ES LA ÚNICA PUERTA que no pasa por nosotros, así que todo lo que la protege
 * tiene que estar probado acá y no confiado a que la dirección sea discreta. Una
 * dirección se comparte, se filtra y se adivina; los topes de RFC-10 no.
 *
 * Y NO CREA NADA EN LA PETICIÓN: encola. Copiar una base tarda segundos y va a
 * tardar más; sostener la petición mientras tanto ocupa un proceso de PHP y una
 * de las 100 conexiones que compartimos con la producción vecina.
 */
class RegistroPublicoTest extends TestCase
{
    use UsaBaseCentral;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'tenancy.dominio_base' => 'demo.test',
            'tenancy.sitio_promocional' => null,
            'tenancy.limites.sal' => 'una-sal-de-prueba',
            'tenancy.limites.tope_ocupados' => 20,
            'tenancy.limites.por_origen' => 3,
        ]);
    }

    private function registrar(array $campos = []): TestResponse
    {
        return $this->post('http://demo.test/guest', array_merge([
            'email' => 'visitante@ejemplo.com',
        ], $campos));
    }

    private function inquilino(TenantEstado $estado, string $email, ?string $origenHash = null): Tenant
    {
        static $n = 0;
        $n++;

        return Tenant::create([
            'slug' => 'reg'.str_pad((string) $n, 9, 'x'),
            'database' => 'demo_probe_reg_'.$n,
            'email' => $email,
            'template_version' => 'demo_template',
            'expira_en' => now()->addDays(30),
            'estado' => $estado,
            'origen_hash' => $origenHash,
        ]);
    }

    public function test_the_central_host_serves_the_form_instead_of_redirecting(): void
    {
        // El host central redirige TODO al sitio promocional cuando hay uno. Sin
        // una excepción explícita, la única puerta de entrada al demo quedaría
        // inalcanzable — y el síntoma sería un 302 en vez de un error.
        config(['tenancy.sitio_promocional' => 'https://www.ejemplo.com']);

        $this->get('http://demo.test/guest')
            ->assertOk()
            ->assertSee('Quiero mi demo');
    }

    public function test_only_the_central_host_serves_the_form(): void
    {
        // UN HOST AJENO AL DOMINIO DEL DEMO no es el central, y a ese no se le
        // toca nada: `ResolveTenant` lo deja pasar tal cual. Sin la comprobación
        // en el controlador, el formulario se serviría desde cualquier nombre
        // que apunte a este servidor.
        //
        // Es el único caso que prueba ESA guarda. Un subdominio inexistente lo
        // corta `ResolveTenant` con 404 antes de llegar acá, y uno de un
        // inquilino real lo cierra `CierraElDemo`: los dos darían verde con la
        // guarda quitada.
        $this->get('http://localhost/guest')->assertNotFound();
    }

    public function test_a_valid_registration_queues_the_alta_instead_of_doing_it(): void
    {
        Queue::fake();

        $this->registrar()->assertRedirect();

        Queue::assertPushedOn('altas', AprovisionaUnInquilino::class, function ($t) {
            return $t->email === 'visitante@ejemplo.com';
        });

        // Y NADA SE CREÓ EN LA PETICIÓN. Si el alta corriera acá, este padrón
        // tendría una fila — y la persona habría esperado varios segundos con
        // una conexión tomada.
        $this->assertSame(0, DB::connection('central')->table('tenants')->count());
    }

    public function test_the_registration_carries_the_origin_so_the_limit_can_count_it(): void
    {
        // Sin el origen, el tope por origen no cuenta nada y el registro queda
        // sin freno — con el padrón diciendo que todo está en orden.
        Queue::fake();

        $this->registrar();

        $esperado = app(LimiteDeAltas::class)->hashDe('127.0.0.1');

        Queue::assertPushed(AprovisionaUnInquilino::class, fn ($t) => $t->origenHash === $esperado);
    }

    public function test_the_origin_limit_refuses_without_queueing_anything(): void
    {
        // Encolar altas que van a fallar es acumular basura: la cola las levanta,
        // el alta revienta contra el tope y queda una fila fallida por cada
        // intento. El tope se comprueba ANTES de encolar (RFC-10, regla 1).
        Queue::fake();

        $hash = app(LimiteDeAltas::class)->hashDe('127.0.0.1');

        foreach (range(1, 3) as $i) {
            $this->inquilino(TenantEstado::Borrado, "previo{$i}@ejemplo.com", $hash);
        }

        $this->registrar()->assertSessionHasErrors('email');

        Queue::assertNothingPushed();
    }

    public function test_the_hard_cap_refuses_without_queueing_anything(): void
    {
        config(['tenancy.limites.tope_ocupados' => 2]);

        Queue::fake();

        $this->inquilino(TenantEstado::Activo, 'uno@ejemplo.com');
        $this->inquilino(TenantEstado::Expirado, 'dos@ejemplo.com');

        $this->registrar()->assertSessionHasErrors('email');

        Queue::assertNothingPushed();
    }

    public function test_an_email_that_already_has_a_demo_is_refused(): void
    {
        Queue::fake();

        $this->inquilino(TenantEstado::Activo, 'visitante@ejemplo.com');

        $this->registrar()->assertSessionHasErrors('email');

        Queue::assertNothingPushed();
    }

    public function test_a_malformed_email_never_reaches_the_queue(): void
    {
        Queue::fake();

        $this->registrar(['email' => 'esto-no-es-un-correo'])->assertSessionHasErrors('email');

        Queue::assertNothingPushed();
    }

    public function test_the_decoy_is_answered_exactly_like_a_real_registration(): void
    {
        // DOS COSAS A LA VEZ, y las dos importan.
        //
        // Que no encole: un robot que llena todo no debe consumir cupo. Y que la
        // respuesta sea IDÉNTICA a la de un alta buena: si difiriera en una coma,
        // esa coma es el indicador que el robot necesita para aprender a evitar
        // el campo.
        Queue::fake();

        $buena = $this->registrar();
        $robot = $this->registrar(['email' => 'robot@ejemplo.com', 'sitio_web' => 'https://spam.example']);

        Queue::assertPushed(AprovisionaUnInquilino::class, 1);

        $this->assertSame($buena->getStatusCode(), $robot->getStatusCode());
        $this->assertSame(
            session()->get('registro.listo'),
            $robot->baseResponse->getSession()?->get('registro.listo'),
        );
        $robot->assertSessionHasNoErrors();
    }

    public function test_the_rate_limiter_does_not_die_against_the_sentinel(): void
    {
        // EL 500 QUE ESTO CIERRA, y salió en producción a los diez minutos de
        // desplegar el registro público.
        //
        // `AppServiceProvider::boot()` llama a `RateLimiter::for()` para los
        // contratos públicos. Eso construye el SINGLETON del limitador durante
        // el arranque —antes de que corra un solo middleware— y su almacén de
        // caché se queda con la conexión que había en ese momento: la por
        // defecto, o sea el centinela.
        //
        // `ResolveTenant` reapunta la conexión después, pero el limitador ya
        // tiene la suya guardada y no se entera nunca. Cualquier ruta con
        // `throttle` muere con «database demo_sin_resolver does not exist».
        //
        // No es sólo esta ruta: los contratos públicos tienen el mismo defecto
        // desde antes, y nadie lo había visto porque su correo nunca llegaba.
        config([
            // LA CONFIGURACIÓN DE PRODUCCIÓN, puesta a mano. La suite corre con
            // `array` en los dos —caché y limitador— para que cada test arranque
            // limpio, y por eso este defecto pasó todos los tests: con `array`
            // no hay ninguna base contra la cual fallar.
            'cache.default' => 'database',
            'cache.limiter' => 'limitador',
            'database.connections.pgsql.database' => 'demo_centinela_de_prueba',
        ]);
        DB::purge('pgsql');

        // El arranque, simulado: el limitador se ata acá.
        $this->app->forgetInstance(RateLimiter::class);
        $this->app->make(RateLimiter::class);

        Queue::fake();

        $this->registrar()->assertRedirect();

        Queue::assertPushed(AprovisionaUnInquilino::class);
    }

    public function test_the_shipped_config_points_the_limiter_at_its_own_store(): void
    {
        // GUARDA DE CABLEADO, y lo digo porque es más débil que el test de
        // arriba: `phpunit.xml` fuerza `CACHE_LIMITER=array` para aislar los
        // tests, así que en la suite nunca se lee el valor real. Sin esta
        // comprobación, alguien podría quitar el default de `config/cache.php`
        // y todo seguiría en verde mientras producción vuelve al 500.
        $this->assertStringContainsString(
            "'limiter' => env('CACHE_LIMITER', 'limitador')",
            (string) file_get_contents(config_path('cache.php')),
            'Sin un almacén propio, el limitador hereda el del arranque: el centinela.',
        );
    }
}
