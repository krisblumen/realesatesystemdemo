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
 * El alta de un demo pedida por un sitio propio (RFC-078), en `/api/demos`.
 *
 * ES LA SEGUNDA PUERTA DE ALTA, y lo que se prueba acá no es que funcione sino
 * que no afloje ninguna de las defensas que ya protegían la primera. Una puerta
 * nueva que salte los topes no es una función: es un agujero con formulario.
 *
 * EL CASO CENTRAL ES EL DEL ORIGEN DECLARADO. Todo RFC-078 existe porque llamar
 * a `/guest` desde el servidor haría que cada alta llegue con la dirección del
 * servidor y el tope por origen corte el embudo al tercer visitante. Si ese test
 * cae, la función no tiene sentido.
 */
class AltaPorApiTest extends TestCase
{
    use UsaBaseCentral;

    private const SECRETO = 'un-secreto-de-prueba-suficientemente-largo';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'tenancy.dominio_base' => 'demo.test',
            'tenancy.sitio_promocional' => null,
            'tenancy.limites.sal' => 'una-sal-de-prueba',
            'tenancy.limites.tope_ocupados' => 20,
            'tenancy.limites.por_origen' => 3,
            'tenancy.api.secreto' => self::SECRETO,
        ]);
    }

    private function pedir(array $campos = [], ?string $secreto = self::SECRETO): TestResponse
    {
        $peticion = $secreto === null ? $this : $this->withToken($secreto);

        return $peticion->postJson('http://demo.test/api/demos', array_merge([
            'email' => 'visitante@ejemplo.com',
            'origen' => '203.0.113.9',
        ], $campos));
    }

    private function inquilino(TenantEstado $estado, string $email, ?string $origenHash = null): Tenant
    {
        static $n = 0;
        $n++;

        return Tenant::create([
            'slug' => 'api'.str_pad((string) $n, 9, 'x'),
            'database' => 'demo_probe_api_'.$n,
            'email' => $email,
            'template_version' => 'demo_template',
            'expira_en' => now()->addDays(30),
            'estado' => $estado,
            'origen_hash' => $origenHash,
        ]);
    }

    public function test_the_door_does_not_exist_until_a_secret_is_configured(): void
    {
        // 404 Y NO 401. Mientras la función no esté en uso no tiene por qué
        // anunciarse: un 401 le confirma a quien recorre rutas que acá hay algo
        // que abrir. Y falla en la dirección correcta — desplegar sin configurar
        // el secreto deja la puerta cerrada, no abierta.
        config(['tenancy.api.secreto' => null]);

        Queue::fake();

        $this->pedir()->assertNotFound();

        Queue::assertNothingPushed();
    }

    public function test_a_request_without_the_secret_is_refused(): void
    {
        Queue::fake();

        $this->pedir(secreto: null)->assertUnauthorized();

        Queue::assertNothingPushed();
    }

    public function test_a_request_with_the_wrong_secret_is_refused(): void
    {
        Queue::fake();

        $this->pedir(secreto: 'no-es-el-secreto')->assertUnauthorized();

        Queue::assertNothingPushed();
    }

    public function test_a_valid_request_queues_the_alta_instead_of_doing_it(): void
    {
        Queue::fake();

        // 202 Y NO 200: cuando esta respuesta sale, el inquilino todavía no
        // existe y el acceso no se mandó.
        $this->pedir()
            ->assertAccepted()
            ->assertJsonPath('estado', 'encolado');

        Queue::assertPushedOn('altas', AprovisionaUnInquilino::class, function ($t) {
            return $t->email === 'visitante@ejemplo.com';
        });

        // Y NADA SE CREÓ EN LA PETICIÓN.
        $this->assertSame(0, DB::connection('central')->table('tenants')->count());
    }

    public function test_the_declared_origin_is_the_one_counted_and_not_the_callers_address(): void
    {
        // ESTE ES EL TEST QUE JUSTIFICA RFC-078. La petición llega desde el
        // servidor de la landing —127.0.0.1 en pruebas— pero el visitante estaba
        // en otra dirección. Si el tope contara al llamador, todas las altas
        // compartirían cupo y el embudo se cortaría solo al tercer visitante del
        // día, sin error visible en ningún lado.
        Queue::fake();

        $this->pedir(['origen' => '198.51.100.4']);

        $delVisitante = app(LimiteDeAltas::class)->hashDe('198.51.100.4');
        $delLlamador = app(LimiteDeAltas::class)->hashDe('127.0.0.1');

        Queue::assertPushed(AprovisionaUnInquilino::class, fn ($t) => $t->origenHash === $delVisitante);
        Queue::assertPushed(AprovisionaUnInquilino::class, fn ($t) => $t->origenHash !== $delLlamador);
    }

    public function test_the_origin_is_required_so_the_limit_cannot_be_switched_off_by_omission(): void
    {
        // Si fuera opcional, olvidarlo apagaría el tope por origen sin que nada
        // fallara. El olvido tiene que doler acá y no en producción.
        Queue::fake();

        $this->pedir(['origen' => null])
            ->assertStatus(422)
            ->assertJsonValidationErrors('origen');

        Queue::assertNothingPushed();
    }

    public function test_a_malformed_origin_never_reaches_the_queue(): void
    {
        Queue::fake();

        $this->pedir(['origen' => 'no-es-una-direccion'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('origen');

        Queue::assertNothingPushed();
    }

    public function test_a_malformed_email_never_reaches_the_queue(): void
    {
        Queue::fake();

        $this->pedir(['email' => 'esto-no-es-un-correo'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        Queue::assertNothingPushed();
    }

    public function test_an_email_that_already_has_a_demo_is_refused_with_conflict(): void
    {
        $this->inquilino(TenantEstado::Activo, 'visitante@ejemplo.com');

        Queue::fake();

        $this->pedir()
            ->assertStatus(409)
            ->assertJsonPath('estado', 'ya_existe');

        Queue::assertNothingPushed();
    }

    public function test_the_origin_limit_refuses_without_queueing_anything(): void
    {
        $hash = app(LimiteDeAltas::class)->hashDe('198.51.100.4');

        for ($i = 0; $i < 3; $i++) {
            $this->inquilino(TenantEstado::Activo, "previo{$i}@ejemplo.com", $hash);
        }

        Queue::fake();

        $this->pedir(['origen' => '198.51.100.4'])
            ->assertStatus(429)
            ->assertJsonPath('estado', 'sin_lugar');

        Queue::assertNothingPushed();
    }

    public function test_the_hard_cap_refuses_without_queueing_anything(): void
    {
        // El tope de la instancia NO lo declara nadie: aunque el llamador tenga
        // el secreto y declare el origen que quiera, este no se puede sortear.
        // Es lo que protege las 100 conexiones que compartimos con la producción
        // vecina.
        config(['tenancy.limites.tope_ocupados' => 1]);

        $this->inquilino(TenantEstado::Activo, 'ocupa@ejemplo.com');

        Queue::fake();

        $this->pedir()
            ->assertStatus(429)
            ->assertJsonPath('estado', 'sin_lugar');

        Queue::assertNothingPushed();
    }

    public function test_only_the_central_host_accepts_altas(): void
    {
        // Dar de alta inquilinos desde el subdominio de un inquilino no significa
        // nada. Un host ajeno al dominio del demo es el caso que prueba ESA
        // guarda: `ResolveTenant` lo deja pasar tal cual.
        Queue::fake();

        $this->withToken(self::SECRETO)
            ->postJson('http://localhost/api/demos', [
                'email' => 'visitante@ejemplo.com',
                'origen' => '203.0.113.9',
            ])
            ->assertNotFound();

        Queue::assertNothingPushed();
    }
}
