<?php

namespace Tests\Feature\Tenancy;

use App\Enums\TenantEstado;
use App\Http\Middleware\RegistraElUso;
use App\Models\Tenant;
use App\Tenancy\InquilinoActual;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\Concerns\UsaBaseCentral;
use Tests\TestCase;

/**
 * El demo al que nadie entra se suelta antes.
 *
 * POR QUÉ EXISTE ESTA REGLA. Con el registro público, la mayoría de las altas no
 * vuelve nunca: alguien deja su correo, mira dos pantallas y se va. Esa base
 * ocupa disco y conexiones —de las 100 que compartimos con la producción
 * vecina— durante todo su plazo, exactamente igual que una que se usa. El
 * recurso escaso no son los inquilinos activos: son las bases vivas.
 *
 * SÓLO ACORTA, NUNCA ALARGA. `expira_en` se fija al crear y nada lo mueve: un
 * demo que se usa vence en su fecha y punto. Esta regla puede adelantarla, no
 * correrla.
 */
class DesusoTest extends TestCase
{
    use UsaBaseCentral;

    protected function setUp(): void
    {
        parent::setUp();

        config(['tenancy.dias_sin_uso' => 5]);
    }

    private function inquilino(?string $ultimoAcceso, ?string $creado = null, ?string $vence = null): Tenant
    {
        static $n = 0;
        $n++;

        $t = Tenant::create([
            'slug' => 'uso'.str_pad((string) $n, 9, 'y'),
            'database' => 'demo_probe_uso_'.$n,
            'email' => "uso{$n}@ejemplo.com",
            'template_version' => 'demo_template',
            'expira_en' => $vence ?? now()->addDays(30),
            'estado' => TenantEstado::Activo,
        ]);

        $t->forceFill(array_filter([
            'ultimo_acceso_en' => $ultimoAcceso,
            'created_at' => $creado,
        ]))->save();

        return $t;
    }

    public function test_a_tenant_nobody_entered_is_released_before_its_date(): void
    {
        // Su fecha está a 30 días. Lo que lo suelta es que nadie entró en 5.
        $abandonado = $this->inquilino(now()->subDays(6)->toDateTimeString());

        $this->artisan('demo:expirar')->assertSuccessful();

        $this->assertSame(TenantEstado::Expirado, $abandonado->fresh()->estado);
    }

    public function test_the_reason_says_it_was_disuse_and_not_the_deadline(): void
    {
        // Un operador que ve un demo cortado antes de su fecha necesita saber
        // cuál de las dos reglas actuó: es la diferencia entre «funcionó» y
        // «bajamos demasiado el número».
        $this->inquilino(now()->subDays(6)->toDateTimeString());

        $this->artisan('demo:expirar')
            ->expectsOutputToContain('nadie entró')
            ->assertSuccessful();
    }

    public function test_a_tenant_in_use_keeps_its_full_term(): void
    {
        $enUso = $this->inquilino(now()->subDay()->toDateTimeString());

        $this->artisan('demo:expirar')->assertSuccessful();

        $this->assertSame(TenantEstado::Activo, $enUso->fresh()->estado);
    }

    public function test_a_tenant_nobody_entered_yet_counts_from_its_creation(): void
    {
        // `ultimo_acceso_en` en null significa que nadie entró NUNCA, y ahí el
        // reloj corre desde el alta. Si `null` se tratara como «hace mucho», un
        // demo recién creado se expiraría antes de que su dueño abra el correo.
        $reciente = $this->inquilino(null);
        $viejo = $this->inquilino(null, now()->subDays(6)->toDateTimeString());

        $this->artisan('demo:expirar')->assertSuccessful();

        $this->assertSame(TenantEstado::Activo, $reciente->fresh()->estado);
        $this->assertSame(TenantEstado::Expirado, $viejo->fresh()->estado);
    }

    public function test_setting_the_limit_to_zero_turns_the_rule_off_instead_of_expiring_everything(): void
    {
        // EL INTERRUPTOR DE APAGADO NO PUEDE SER EL BOTÓN DE DEMOLICIÓN.
        //
        // Sin una salida explícita, cero significa «sin uso desde hace 0 días»,
        // que es todo lo activo. Quien lo ponga en cero para desactivar la regla
        // —lo razonable— borraría el demo entero en el siguiente barrido.
        config(['tenancy.dias_sin_uso' => 0]);

        $abandonado = $this->inquilino(now()->subDays(90)->toDateTimeString());

        $this->artisan('demo:expirar')->assertSuccessful();

        $this->assertSame(TenantEstado::Activo, $abandonado->fresh()->estado);
    }

    public function test_using_it_does_not_buy_more_time_than_the_original_term(): void
    {
        // La regla acorta, no alarga. Un demo usado ayer pero pasado de fecha
        // vence igual: el plazo que se prometió es el techo.
        $usadoPeroVencido = $this->inquilino(
            now()->subDay()->toDateTimeString(),
            vence: now()->subDay()->toDateTimeString(),
        );

        $this->artisan('demo:expirar')->assertSuccessful();

        $this->assertSame(TenantEstado::Expirado, $usadoPeroVencido->fresh()->estado);
    }

    public function test_entering_the_panel_records_the_use(): void
    {
        $tenant = $this->inquilino(now()->subDays(6)->toDateTimeString());

        app(InquilinoActual::class)->fijar($tenant);

        $this->pasarPorElMedio();

        $this->assertTrue(
            $tenant->fresh()->ultimo_acceso_en->isAfter(now()->subMinute()),
            'Sin esto, un demo que se usa todos los días se expiraría por desuso.',
        );
    }

    public function test_it_does_not_write_on_every_single_request(): void
    {
        // Un panel activo hace decenas de peticiones por minuto, todas diciendo
        // lo mismo. Escribirlas todas es un UPDATE a la central por cada clic —
        // castigar el uso normal para ganar una precisión de minutos en un tope
        // que se mide en días.
        $tenant = $this->inquilino(now()->subMinutes(2)->toDateTimeString());

        app(InquilinoActual::class)->fijar($tenant);

        $antes = $tenant->fresh()->ultimo_acceso_en;

        $this->pasarPorElMedio();

        $this->assertEquals($antes, $tenant->fresh()->ultimo_acceso_en);
    }

    public function test_without_a_tenant_it_writes_nothing(): void
    {
        // El host central pasa por acá sin inquilino. Anotar algo ahí sería
        // anotarlo sobre quien fuera que quedó en memoria.
        $tenant = $this->inquilino(now()->subDays(6)->toDateTimeString());

        $antes = $tenant->fresh()->ultimo_acceso_en;

        $this->pasarPorElMedio();

        $this->assertEquals($antes, $tenant->fresh()->ultimo_acceso_en);
    }

    public function test_the_login_screen_does_not_count_as_use(): void
    {
        // ESTO LO DECIDE DÓNDE ESTÁ REGISTRADO, no el código del middleware.
        //
        // En `authMiddleware` corre después de autenticar; en la lista de arriba
        // correría para todo el panel, incluida la pantalla de login. Un robot
        // golpeando el login mantendría vivo un demo que nadie usa.
        //
        // Es una comprobación de cableado y no de conducta: lo que prueba es que
        // nadie mueva esa línea de lista sin darse cuenta.
        $panel = filament()->getPanel('admin');

        $this->assertContains(RegistraElUso::class, $panel->getAuthMiddleware());
        $this->assertNotContains(RegistraElUso::class, $panel->getMiddleware());
    }

    public function test_the_padron_shows_how_long_it_has_been_idle(): void
    {
        // Desde el padrón, «expirado por fecha» y «expirado por desuso» se ven
        // exactamente igual. Sin este dato, entender por qué se cayó un demo
        // obliga a consultar la base a mano.
        $this->inquilino(now()->subDays(3)->toDateTimeString());
        $this->inquilino(null);

        $this->artisan('demo:padron')
            ->expectsOutputToContain('hace 3 d')
            ->expectsOutputToContain('nunca')
            ->assertSuccessful();
    }

    private function pasarPorElMedio(): void
    {
        (new RegistraElUso)->handle(Request::create('/admin'), fn () => new Response);
    }
}
