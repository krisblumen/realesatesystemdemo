<?php

namespace Tests\Feature\Frontend;

use App\Filament\Resources\FrontendServiceResource\Pages\EditFrontendService;
use App\Models\FrontendService;
use App\Models\User;
use App\Services\Frontend\FrontendCacheGeneration;
use App\Services\Frontend\FrontendServicesService;
use App\Services\Frontend\Media\ServiceMediaReference;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Épica 12.3 — T3-17: `image_alt` obligatorio cuando hay imagen (§10.2).
 *
 * La regla universal de accesibilidad ya rige para toda media de secciones. Que
 * servicios quedara afuera significaba dos reglas distintas en el mismo panel, y
 * una foto sin describir para quien usa un lector de pantalla.
 *
 * Lo que NO hace: romper registros existentes. El requisito vive en el guardado,
 * y el render conserva su fallback al título.
 */
class FrontendServiceAltTextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('frontend-private');
        $this->seed(PermissionSeeder::class);
        $this->actingAs(User::factory()->withRole('owner')->create());
    }

    private function service(): FrontendService
    {
        return FrontendService::query()->firstOrFail();
    }

    private function editor(FrontendService $service): Testable
    {
        return Livewire::test(EditFrontendService::class, ['record' => $service->getKey()]);
    }

    public function test_saving_an_image_without_alt_is_rejected(): void
    {
        $this->editor($this->service())
            ->set('data.image_alt', '')
            ->set('data.image', [UploadedFile::fake()->image('foto.png', 1200, 800)])
            ->call('save')
            ->assertHasFormErrors(['image_alt']);
    }

    public function test_saving_an_image_with_alt_is_accepted(): void
    {
        $service = $this->service();

        $this->editor($service)
            ->set('data.image_alt', 'Fachada de una casa entregada en Juriquilla')
            ->set('data.image', [UploadedFile::fake()->image('foto.png', 1200, 800)])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNotNull($service->fresh()->image_media_id);
        $this->assertSame('Fachada de una casa entregada en Juriquilla', $service->fresh()->image_alt);
    }

    public function test_a_service_without_image_needs_no_alt(): void
    {
        // El requisito es condicional: sin foto no hay nada que describir.
        $this->editor($this->service())
            ->set('data.image_alt', '')
            ->call('save')
            ->assertHasNoFormErrors();
    }

    public function test_html_in_the_alt_is_rejected(): void
    {
        $this->editor($this->service())
            ->set('data.image_alt', '<script>alert(1)</script>')
            ->set('data.image', [UploadedFile::fake()->image('foto.png', 1200, 800)])
            ->call('save')
            ->assertHasFormErrors(['image_alt']);
    }

    public function test_an_existing_service_without_alt_still_renders(): void
    {
        // Datos previos a esta regla: el requisito vive en el guardado, no en la
        // lectura. Una ficha vieja sin alt no puede dejar de mostrarse.
        $service = $this->service();

        $media = $service->addMedia(UploadedFile::fake()->image('vieja.png', 1200, 800))
            ->toMediaCollection(ServiceMediaReference::COLLECTION);

        DB::table('frontend_services')->where('id', $service->getKey())->update([
            'image_media_id' => (string) $media->uuid,
            'image_alt' => null,
        ]);

        app(FrontendCacheGeneration::class)->bump();

        $ficha = collect(app(FrontendServicesService::class)->services('servicios'))
            ->firstWhere('code', $service->service_type_code);

        // El fallback vigente: el alt cae al título del servicio.
        $this->assertSame($service->title, $ficha['image_alt']);

        $this->get('/servicios')->assertOk()->assertSee($service->title, escape: false);
    }
}
