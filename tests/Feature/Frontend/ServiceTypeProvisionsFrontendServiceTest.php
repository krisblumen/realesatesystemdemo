<?php

namespace Tests\Feature\Frontend;

use App\Actions\Frontend\ProvisionFrontendServiceForType;
use App\Filament\Resources\FrontendServiceResource;
use App\Filament\Resources\FrontendServiceResource\Pages\EditFrontendService;
use App\Filament\Resources\ServiceTypeResource\Pages\CreateServiceType;
use App\Models\FrontendService;
use App\Models\ServiceType;
use App\Models\User;
use App\Services\Frontend\FrontendServicesService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * `FrontendService` existe 1:1 con `ServiceType` (RFC-074) y
 * `FrontendServiceResource::canCreate()` es `false` a propósito — el owner
 * nunca da de alta un servicio a mano, edita contenido de uno ya existente.
 *
 * Antes de este cambio, esa regla dejaba un agujero real: crear un TIPO nuevo
 * desde el panel no dejaba forma de cargarle contenido, porque no existía
 * ningún `FrontendService` que editar. El tipo quedaba «inelegible para
 * siempre» (RFC-074 M-2: «ausencia de FrontendService = inelegible») sin
 * ningún error que lo explicara — simplemente no aparecía en «Servicios del
 * sitio».
 *
 * Lo que se prueba acá es que crear el TIPO deja un servicio EDITABLE con la
 * misma estructura que los que ya existen, y que arranca oculto: sin
 * descripción, ícono ni foto propios, mostrarlo de entrada publicaría una
 * tarjeta a medio llenar en el sitio en vivo.
 */
class ServiceTypeProvisionsFrontendServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->actingAs(User::factory()->withRole('owner')->create());
    }

    public function test_creating_a_service_type_provisions_its_frontend_service(): void
    {
        Livewire::test(CreateServiceType::class)
            ->fillForm([
                'code' => 'legal',
                'label' => 'Asesoría legal',
                'color' => 'info',
                'sort_order' => 5,
                'active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $servicio = FrontendService::query()->where('service_type_code', 'legal')->first();

        $this->assertNotNull($servicio, 'Crear el tipo no dejó ningún servicio para editar.');
        $this->assertSame('Asesoría legal', $servicio->title);
        $this->assertSame(5, $servicio->sort_order);
    }

    public function test_the_provisioned_service_starts_hidden_in_both_locations(): void
    {
        // El caso que este test evita: un tipo recién creado, sin contenido
        // propio, publicándose solo en el sitio en vivo.
        Livewire::test(CreateServiceType::class)
            ->fillForm(['code' => 'legal', 'label' => 'Asesoría legal', 'color' => 'info', 'active' => true])
            ->call('create');

        $servicio = FrontendService::query()->where('service_type_code', 'legal')->firstOrFail();

        $this->assertFalse($servicio->show_in_home);
        $this->assertFalse($servicio->show_in_services);

        // Y ninguna de las dos consultas públicas lo devuelve todavía.
        $servicios = app(FrontendServicesService::class);
        $tieneAsesoriaLegal = fn (array $lista): bool => in_array(
            'Asesoría legal',
            array_column($lista, 'title'),
            true,
        );

        $this->assertFalse($tieneAsesoriaLegal($servicios->services('home')));
        $this->assertFalse($tieneAsesoriaLegal($servicios->services('servicios')));
    }

    public function test_the_provisioned_service_is_editable_with_the_same_form_as_the_others(): void
    {
        // «La misma estructura que los que ya existen»: el editor no distingue
        // un servicio recién provisto de uno sembrado desde el inicio.
        Livewire::test(CreateServiceType::class)
            ->fillForm(['code' => 'legal', 'label' => 'Asesoría legal', 'color' => 'info', 'active' => true])
            ->call('create');

        $servicio = FrontendService::query()->where('service_type_code', 'legal')->firstOrFail();

        Livewire::test(EditFrontendService::class, ['record' => $servicio->getKey()])
            ->assertFormFieldExists('title')
            ->assertFormFieldExists('icon')
            ->assertFormFieldExists('short_description')
            ->set('data.short_description', 'Contratos, arrendamientos y trámites.')
            ->set('data.icon', 'file-text')
            ->set('data.show_in_services', true)
            ->call('save')
            ->assertHasNoFormErrors();

        $servicio->refresh();
        $this->assertSame('Contratos, arrendamientos y trámites.', $servicio->short_description);
        $this->assertSame('file-text', $servicio->icon);
        $this->assertTrue($servicio->show_in_services);
    }

    public function test_provisioning_is_not_destructive_if_it_ran_before(): void
    {
        // insert-if-missing, igual que SeedInversionService: no puede pisar un
        // servicio que el owner ya haya personalizado.
        $type = ServiceType::query()->create([
            'code' => 'legal',
            'label' => 'Asesoría legal',
            'color' => 'info',
            'sort_order' => 0,
            'active' => true,
        ]);

        $servicio = FrontendService::query()->create([
            'service_type_code' => 'legal',
            'title' => 'Un título que el owner ya escribió',
            'show_in_home' => true,
            'show_in_services' => true,
            'allow_leads' => true,
            'sort_order' => 0,
        ]);

        app(ProvisionFrontendServiceForType::class)->run($type);

        $servicio->refresh();
        $this->assertSame('Un título que el owner ya escribió', $servicio->title);
        $this->assertTrue($servicio->show_in_home, 'La reprovisión apagó un servicio que el owner ya había encendido.');
        $this->assertSame(1, FrontendService::query()->where('service_type_code', 'legal')->count());
    }

    public function test_the_notification_points_the_owner_to_where_to_finish_it(): void
    {
        Livewire::test(CreateServiceType::class)
            ->fillForm(['code' => 'legal', 'label' => 'Asesoría legal', 'color' => 'info', 'active' => true])
            ->call('create')
            ->assertNotified('Tipo de servicio creado');

        // El cuerpo real —adónde ir a completar el contenido— sale del propio
        // método de la página, sin depender de la sesión-flash interna de
        // Filament que `assertNotified()` ya consumió arriba. El método no
        // toca ningún estado del componente, así que una instancia sin montar
        // alcanza.
        $pagina = new CreateServiceType;
        $cuerpo = (new \ReflectionMethod($pagina, 'getCreatedNotification'))
            ->invoke($pagina)
            ?->getBody();

        $this->assertNotNull($cuerpo);
        $this->assertStringContainsString('Servicios del sitio', $cuerpo);
    }

    public function test_frontend_service_resource_still_cannot_create_records_directly(): void
    {
        // La regla de fondo no cambió: el owner nunca da de alta un servicio a
        // mano, sólo edita contenido de uno que ya existe.
        $this->assertFalse(FrontendServiceResource::canCreate());
    }
}
