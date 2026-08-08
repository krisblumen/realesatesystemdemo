<?php

namespace Tests\Feature\Tenancy;

use App\Enums\TenantEstado;
use App\Jobs\AprovisionaUnInquilino;
use App\Models\Tenant;
use App\Tenancy\LimiteDeAltas;
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
}
