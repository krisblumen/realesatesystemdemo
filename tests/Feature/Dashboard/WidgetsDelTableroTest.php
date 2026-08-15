<?php

namespace Tests\Feature\Dashboard;

use App\Enums\EstadoContrato;
use App\Enums\UserStatus;
use App\Enums\ZoneStatus;
use App\Filament\Widgets\AccionesRapidasWidget;
use App\Filament\Widgets\ContratosEnProcesoWidget;
use App\Filament\Widgets\ZonasActivasWidget;
use App\Models\ContratoIntermediacion;
use App\Models\Property;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Los dos bloques que el tablero no tenía y la landing sí promete.
 *
 * POR QUÉ IMPORTAN. Quien entra al demo vio antes el tablero de la landing. Si
 * acá se encuentra otra cosa, la primera impresión no es «faltan widgets»: es
 * que le mostraron algo que no existe.
 */
class WidgetsDelTableroTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    private function usuario(string $rol): User
    {
        $usuario = User::create([
            'name' => 'Quien opera',
            'email' => $rol.'@landra.test',
            'password' => 'una-contrasena-larga',
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ]);

        $usuario->assignRole($rol);

        return $usuario;
    }

    private function contrato(EstadoContrato $estado, ?string $vigenciaFin = null): ContratoIntermediacion
    {
        $contrato = ContratoIntermediacion::factory()->enEstado($estado)->create();

        if ($vigenciaFin !== null) {
            $contrato->forceFill(['vigencia_fin' => $vigenciaFin])->save();
        }

        return $contrato;
    }

    public function test_a_contract_that_is_still_moving_appears_as_in_progress(): void
    {
        $this->actingAs($this->usuario('owner'));

        $enviado = $this->contrato(EstadoContrato::Enviado);

        Livewire::test(ContratosEnProcesoWidget::class)
            ->assertSee($enviado->folio)
            ->assertSee('En proceso');
    }

    public function test_a_signed_contract_about_to_expire_is_the_one_that_matters(): void
    {
        // ESTE AVISO NO EXISTÍA EN NINGUNA PANTALLA, y es el que evita que a una
        // inmobiliaria se le caiga una exclusiva sin darse cuenta. Es lo que
        // convierte este widget en algo útil y no en un listado más.
        $this->actingAs($this->usuario('owner'));

        $porVencer = $this->contrato(EstadoContrato::Firmado, now()->addDays(10)->toDateString());

        Livewire::test(ContratosEnProcesoWidget::class)
            ->assertSee($porVencer->folio)
            ->assertSee('Por vencer');
    }

    public function test_a_closed_contract_does_not_ask_for_attention(): void
    {
        // Un firmado con vigencia larga, un rechazado y un cancelado están
        // cerrados —cada uno a su manera— y no piden nada. Listarlos convertiría
        // el bloque en el historial completo, que ya existe en su propia página.
        $this->actingAs($this->usuario('owner'));

        $lejos = $this->contrato(EstadoContrato::Firmado, now()->addYear()->toDateString());
        $rechazado = $this->contrato(EstadoContrato::Rechazado);
        $cancelado = $this->contrato(EstadoContrato::Cancelado);

        $vista = Livewire::test(ContratosEnProcesoWidget::class);

        $vista->assertDontSee($lejos->folio);
        $vista->assertDontSee($rechazado->folio);
        $vista->assertDontSee($cancelado->folio);
    }

    public function test_what_expires_first_is_shown_first(): void
    {
        // Un aviso que llega tarde no es un aviso. Con el corte en cinco
        // renglones, el orden decide qué se ve y qué no.
        $this->actingAs($this->usuario('owner'));

        $porVencer = $this->contrato(EstadoContrato::Firmado, now()->addDays(3)->toDateString());
        $this->contrato(EstadoContrato::Enviado);

        $contratos = Livewire::test(ContratosEnProcesoWidget::class)->instance()->getContratos();

        $this->assertSame($porVencer->folio, $contratos->first()['folio']);
    }

    public function test_the_empty_state_says_what_is_missing_instead_of_nothing(): void
    {
        // En un demo recién creado esta es de las primeras pantallas que se ven.
        // «Sin datos» ahí parece una función rota en vez de una lista que
        // todavía nadie llenó.
        $this->actingAs($this->usuario('owner'));

        Livewire::test(ContratosEnProcesoWidget::class)
            ->assertSee('Cuando generes uno desde un inmueble');
    }

    public function test_the_quick_actions_are_there_for_whoever_can_use_them(): void
    {
        $this->actingAs($this->usuario('owner'));

        Livewire::test(AccionesRapidasWidget::class)
            ->assertSee('Nueva propiedad')
            ->assertSee('Registrar lead')
            ->assertSee('Generar contrato');
    }

    public function test_without_permissions_the_card_is_not_drawn_at_all(): void
    {
        // DOS COSAS. Que no aparezca un botón que lleva a un 403 —la primera
        // lectura de eso no es «no tengo permiso», es «esto está roto»—, y que
        // sin ninguna acción disponible no quede una tarjeta titulada y vacía,
        // que ocupa lugar y parece algo que no cargó.
        //
        // Se usa alguien SIN rol porque hoy `owner` y `agente` pueden crear las
        // tres cosas: con cualquiera de los dos, este test pasaría sin comprobar
        // la guarda. El día que exista un rol de sólo lectura, esa guarda ya está.
        $sinPermisos = User::create([
            'name' => 'Sin permisos',
            'email' => 'miron@landra.test',
            'password' => 'una-contrasena-larga',
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($sinPermisos);

        $this->assertSame([], app(AccionesRapidasWidget::class)->getAcciones());
        $this->assertFalse(
            AccionesRapidasWidget::canView(),
            'Sin ninguna acción posible, el widget no tiene que dibujarse en el tablero.',
        );
    }

    public function test_the_zone_list_orders_by_inventory_and_ignores_inactive_ones(): void
    {
        // LO QUE ESTE BLOQUE RESPONDE, y que las tarjetas de `ZonesOverviewWidget`
        // no responden: no cuántas zonas hay, sino DÓNDE está el inventario. Tres
        // zonas con 38, 27 y 21 inmuebles cuentan una historia que «12 zonas
        // activas» no cuenta.
        $this->actingAs($this->usuario('owner'));

        $chica = Zone::factory()->withPolygon()->create(['name' => 'Zona Chica', 'status' => ZoneStatus::Active]);
        $grande = Zone::factory()->withPolygon()->create(['name' => 'Zona Grande', 'status' => ZoneStatus::Active]);
        $apagada = Zone::factory()->withPolygon()->create(['name' => 'Zona Apagada', 'status' => ZoneStatus::Inactive]);

        Property::factory()->count(1)->create(['zone_id' => $chica->id]);
        Property::factory()->count(3)->create(['zone_id' => $grande->id]);
        Property::factory()->count(9)->create(['zone_id' => $apagada->id]);

        $zonas = Livewire::test(ZonasActivasWidget::class)->instance()->getZonas();

        // La que más inventario tiene, primero.
        $this->assertSame('Zona Grande', $zonas->first()['nombre']);
        $this->assertSame(3, $zonas->first()['inmuebles']);

        // Y una zona apagada no cuenta, por más inmuebles que tenga colgando:
        // el bloque se llama «activas» y tiene que decir la verdad.
        $this->assertNotContains('Zona Apagada', $zonas->pluck('nombre')->all());
        $this->assertSame(2, Livewire::test(ZonasActivasWidget::class)->instance()->getTotalDeZonas());
    }
}
