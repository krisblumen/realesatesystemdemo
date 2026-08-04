<?php

namespace Tests\Feature\Frontend;

use App\Models\FrontendPage;
use App\Models\FrontendService;
use App\Models\User;
use App\Services\Frontend\FrontendPagePublisher;
use App\Services\Frontend\Media\MediaPromotionState;
use App\Services\Frontend\Media\ServiceMediaReference;
use App\Services\Frontend\SyncFrontendServiceImage;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

/**
 * Publicar deja la foto visible EN EL ACTO, sin depender de un worker.
 *
 * El caso real que lo motivó, dos veces seguidas: el owner subía imágenes al
 * hero, publicaba, el panel decía «Publicada» y el sitio seguía sin las fotos.
 * No era un bug de render —la copia al disco público iba por cola y no había
 * ningún worker vivo—, pero el resultado para él era idéntico a uno: nada
 * aparecía y nada lo avisaba. La red de rescate tampoco servía, porque la
 * reconciliación corre por el scheduler, que necesita otro proceso vivo.
 *
 * Copiar cuesta milisegundos por imagen (medido: 11 a 44 ms), muchísimo menos
 * que el problema que la asincronía evitaba.
 *
 * Lo que NO cambió, y por eso hay tests: la copia sigue ocurriendo FUERA de la
 * transacción —el sistema de archivos no participa de un rollback— y un fallo
 * de copia no puede tumbar una publicación ya confirmada.
 */
class FrontendImmediatePromotionTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('frontend-private');

        // EL ESCENARIO DEL PROBLEMA: cola `database` y nadie procesándola. En
        // tests la cola es `sync` por defecto, así que un `dispatch()` normal
        // correría igual y esta clase no probaría nada.
        //
        // `Queue::fake()` tampoco sirve acá: intercepta también la ejecución
        // síncrona, así que con fake NADA corre y el test no distingue una
        // implementación de la otra.
        config(['queue.default' => 'database']);

        $this->seed(PermissionSeeder::class);
        $this->owner = User::factory()->withRole('owner')->create();
        $this->actingAs($this->owner);
    }

    private function heroWithPhoto(): array
    {
        $page = FrontendPage::query()->where('key', 'home')->firstOrFail();
        $hero = $page->sections()->where('section_key', 'hero')->firstOrFail();

        $media = $hero->addMedia(UploadedFile::fake()->image('fondo.png', 1600, 900))
            ->toMediaCollection('images');

        $hero->forceFill(['payload' => [
            'title' => 'Con foto',
            'text_align' => 'left', 'logo_enabled' => false, 'logo_size' => 'md',
            'slides' => [[
                'media_id' => (string) $media->uuid,
                'alt' => null, 'decorative' => true, 'sort_order' => 0,
            ]],
        ]])->saveQuietly();

        return [$page->fresh(), $media];
    }

    // ------------------------------------------------ lo que se pidió ----

    public function test_publishing_makes_the_photo_public_without_any_worker(): void
    {
        [$page, $media] = $this->heroWithPhoto();

        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, $this->owner);

        $media->refresh();
        $this->assertTrue(app(MediaPromotionState::class)->isPromoted($media), 'La foto quedó esperando un worker.');
        $this->assertSame('public', $media->disk);
        $this->assertTrue(Storage::disk('public')->exists($media->getPathRelativeToRoot()));
    }

    public function test_nothing_is_left_waiting_on_the_queue(): void
    {
        [$page] = $this->heroWithPhoto();
        $antes = DB::table('jobs')->count();

        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, $this->owner);

        // Nada queda esperando a que alguien lo procese después.
        $this->assertSame($antes, DB::table('jobs')->count());
    }

    public function test_the_public_render_shows_the_photo_right_after_publishing(): void
    {
        [$page] = $this->heroWithPhoto();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, $this->owner);

        $this->get('/')->assertOk()->assertSee('/storage/', escape: false);
    }

    public function test_saving_a_service_image_publishes_it_immediately_too(): void
    {
        $service = FrontendService::query()->firstOrFail();
        $service->addMedia(UploadedFile::fake()->image('svc.png', 1200, 800))
            ->toMediaCollection(ServiceMediaReference::COLLECTION);

        app(SyncFrontendServiceImage::class)($service);

        $media = Media::query()->where('uuid', $service->fresh()->image_media_id)->firstOrFail();

        $this->assertTrue(app(MediaPromotionState::class)->isPromoted($media));
        $this->assertSame('public', $media->disk);
    }

    // --------------------------------------- lo que NO debía cambiar ----

    public function test_a_failed_copy_never_breaks_an_already_committed_publish(): void
    {
        // Se borra el archivo del disco privado ANTES de publicar: la copia va a
        // fallar. La publicación ya está commiteada y no puede caerse por eso.
        [$page, $media] = $this->heroWithPhoto();
        Storage::disk('frontend-private')->delete($media->getPathRelativeToRoot());

        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, $this->owner);

        // La página quedó publicada…
        $this->assertFalse($page->fresh()->hasUnpublishedChanges());

        // …y la foto quedó marcada para que la reconciliación la retome.
        $media->refresh();
        $this->assertFalse(app(MediaPromotionState::class)->isPromoted($media));
        $this->assertTrue(app(MediaPromotionState::class)->isPending($media));
    }

    public function test_the_reconciliation_still_recovers_a_deferred_promotion(): void
    {
        // La red de rescate sigue existiendo: es lo que levanta una copia que
        // falló, ahora que no hay una cola donde reintentar sola.
        [$page, $media] = $this->heroWithPhoto();
        $ruta = $media->getPathRelativeToRoot();
        $bytes = Storage::disk('frontend-private')->get($ruta);

        Storage::disk('frontend-private')->delete($ruta);
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, $this->owner);
        $this->assertFalse(app(MediaPromotionState::class)->isPromoted($media->refresh()));

        // Vuelve el archivo y corre la reconciliación.
        Storage::disk('frontend-private')->put($ruta, $bytes);
        $this->artisan('frontend:media:reconcile')->assertSuccessful();

        $this->assertTrue(app(MediaPromotionState::class)->isPromoted($media->refresh()));
    }

    public function test_a_rolled_back_publish_promotes_nothing(): void
    {
        // La copia sigue corriendo DESPUÉS del commit: el sistema de archivos no
        // participa de un rollback, así que copiar antes dejaría archivos
        // públicos huérfanos de una publicación que nunca ocurrió.
        [$page, $media] = $this->heroWithPhoto();

        try {
            // Una revisión de borrador equivocada aborta la publicación.
            app(FrontendPagePublisher::class)->publish($page, $page->draft_revision + 99, $this->owner);
        } catch (\Throwable) {
            // Esperado.
        }

        $this->assertFalse(app(MediaPromotionState::class)->isPromoted($media->refresh()));
        $this->assertFalse(Storage::disk('public')->exists($media->getPathRelativeToRoot()));
    }
}
