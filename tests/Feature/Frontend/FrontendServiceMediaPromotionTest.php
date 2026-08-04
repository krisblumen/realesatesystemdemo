<?php

namespace Tests\Feature\Frontend;

use App\Jobs\PromoteFrontendMedia;
use App\Models\FrontendService;
use App\Services\Frontend\FrontendCacheGeneration;
use App\Services\Frontend\FrontendServicesService;
use App\Services\Frontend\Media\MediaPromotionState;
use App\Services\Frontend\Media\PromotableMediaOwners;
use App\Services\Frontend\Media\ServiceMediaReference;
use App\Services\Frontend\SyncFrontendServiceImage;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

/**
 * Épica 12.3, Lote B — T3-1…T3-18: la imagen de un servicio deja de ser pública
 * desde que se sube.
 *
 * El lote existe por una fuga concreta: como la colección no borra, cambiar la
 * foto de un servicio dejaba la anterior accesible en `/storage` para siempre.
 * Con el pipeline privado, dejar de referenciar ES dejar de ser accesible.
 */
class FrontendServiceMediaPromotionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('frontend-private');
        $this->seed(PermissionSeeder::class);
    }

    private function service(): FrontendService
    {
        return FrontendService::query()->firstOrFail();
    }

    /**
     * En tests la cola es `sync`, así que `dispatch()->afterCommit()` ejecuta el
     * job EN EL ACTO y nunca se observa el estado intermedio. Los tests que miran
     * el borrador antes de promover fingen la cola; los que miran el resultado
     * final la dejan correr.
     */
    private function withoutRunningTheJob(): void
    {
        Queue::fake();
    }

    private function attach(FrontendService $service, string $name = 'foto.png'): Media
    {
        return $service->addMedia(UploadedFile::fake()->image($name, 1200, 800))
            ->toMediaCollection(ServiceMediaReference::COLLECTION);
    }

    /** Sube una imagen y corre la secuencia de guardado real. */
    private function save(FrontendService $service, string $name = 'foto.png'): Media
    {
        $media = $this->attach($service, $name);
        app(SyncFrontendServiceImage::class)($service);

        return $media->refresh();
    }

    private function promote(Media $media): void
    {
        app()->call([new PromoteFrontendMedia((string) $media->uuid), 'handle']);
    }

    // -------------------------------------------------------------- T3-1 ----

    public function test_a_new_image_lands_on_the_private_disk_and_is_not_public_yet(): void
    {
        $this->withoutRunningTheJob();
        $service = $this->service();
        $media = $this->save($service);

        $this->assertSame('frontend-private', $media->disk);
        $this->assertFalse(Storage::disk('public')->exists($media->getPathRelativeToRoot()));

        // Y el render NO la emite: sólo lo `promoted` llega al HTML público.
        $this->assertNull($this->renderedImageUrl($service->fresh()));
    }

    private function renderedImageUrl(FrontendService $service): ?string
    {
        $servicios = app(FrontendServicesService::class)->services('servicios');

        return collect($servicios)->firstWhere('code', $service->service_type_code)['image_url'] ?? null;
    }

    public function test_the_image_becomes_public_only_after_promotion(): void
    {
        $service = $this->service();
        $media = $this->save($service);

        $this->promote($media);

        $media->refresh();
        $this->assertSame('public', $media->disk);
        $this->assertTrue(app(MediaPromotionState::class)->isPromoted($media));
        $this->assertTrue(Storage::disk('public')->exists($media->getPathRelativeToRoot()));

        app(FrontendCacheGeneration::class)->bump();
        $this->assertNotNull($this->renderedImageUrl($service->fresh()));
    }

    // -------------------------------------------------------------- T3-8 ----

    public function test_a_normal_save_marks_pending_and_queues_the_job_after_commit(): void
    {
        Queue::fake();

        $service = $this->service();
        $media = $this->save($service);

        $this->assertTrue(app(MediaPromotionState::class)->isPending($media));
        $this->assertSame((string) $media->uuid, $service->fresh()->image_media_id);

        // Encolado, no ejecutado a mano: es la diferencia entre probar el flujo
        // y probar el job.
        Queue::assertPushed(PromoteFrontendMedia::class, fn (PromoteFrontendMedia $job): bool => $job->uuid === (string) $media->uuid);
    }

    public function test_an_ineligible_candidate_leaves_the_service_without_image(): void
    {
        $service = $this->service();

        // Media de OTRO servicio: la validación de frontera la rechaza y la
        // columna queda en null en vez de apuntar a algo ajeno.
        $otro = FrontendService::query()->where('id', '!=', $service->getKey())->firstOrFail();
        $this->attach($otro, 'ajena.png');

        app(SyncFrontendServiceImage::class)($service);

        $this->assertNull($service->fresh()->image_media_id);
    }

    // -------------------------------------------------------------- T3-7 ----

    public function test_replacing_the_photo_keeps_the_previous_row_and_file(): void
    {
        $service = $this->service();
        $primera = $this->save($service, 'primera.png');
        $this->promote($primera);

        $segunda = $this->save($service->fresh(), 'segunda.png');

        $this->assertSame((string) $segunda->uuid, $service->fresh()->image_media_id);

        // La anterior sobrevive: fila y archivo. Es la razón de no usar
        // singleFile() ni el uploader destructivo.
        $this->assertNotNull(Media::query()->where('uuid', $primera->uuid)->first());
        $this->assertTrue(Storage::disk('public')->exists($primera->refresh()->getPathRelativeToRoot()));
    }

    public function test_the_superseded_photo_stops_being_current(): void
    {
        $service = $this->service();
        $primera = $this->save($service, 'primera.png');
        $segunda = $this->save($service->fresh(), 'segunda.png');

        $owner = app(ServiceMediaReference::class);
        $locked = $service->fresh();

        $this->assertFalse($owner->isReferencedByLiveContent((string) $primera->uuid, $locked));
        $this->assertTrue($owner->isReferencedByLiveContent((string) $segunda->uuid, $locked));
    }

    public function test_replacing_before_promotion_clears_the_previous_pending_flag(): void
    {
        $this->withoutRunningTheJob();
        $service = $this->service();
        $primera = $this->save($service, 'primera.png');

        $this->assertTrue(app(MediaPromotionState::class)->isPending($primera));

        $this->save($service->fresh(), 'segunda.png');

        // La primera nunca llegó a promoverse y ya no está referenciada: su flag
        // se limpia en el mismo guardado, sin esperar a la reconciliación.
        $this->assertFalse(app(MediaPromotionState::class)->isPending($primera->refresh()));
    }

    // ------------------------------------------------------------- T3-13 ----

    public function test_a_job_whose_reference_was_dropped_does_not_promote(): void
    {
        $this->withoutRunningTheJob();
        $service = $this->service();
        $primera = $this->save($service, 'primera.png');

        // Se reemplaza la foto DESPUÉS de encolar: al correr, el job relee la
        // columna bajo el lock y cancela. Sin esa relectura promovería una imagen
        // que ya nadie referencia.
        $this->save($service->fresh(), 'segunda.png');

        $this->promote($primera);

        $primera->refresh();
        $this->assertFalse(app(MediaPromotionState::class)->isPromoted($primera));
        $this->assertSame('frontend-private', $primera->disk);
    }

    public function test_promotion_is_idempotent(): void
    {
        $service = $this->service();
        $media = $this->save($service);

        $this->promote($media);
        $this->promote($media);

        $media->refresh();
        $this->assertSame('public', $media->disk);
        $this->assertTrue(app(MediaPromotionState::class)->isPromoted($media));
        $this->assertFalse(app(MediaPromotionState::class)->isPending($media));
    }

    // -------------------------------------------------------------- T3-9 ----

    public function test_a_soft_deleted_service_stops_being_current(): void
    {
        $service = $this->service();
        $media = $this->save($service);

        $service->delete();

        $owner = app(ServiceMediaReference::class);
        $locked = FrontendService::withTrashed()->whereKey($service->getKey())->firstOrFail();

        $this->assertFalse($owner->isReferencedByLiveContent((string) $media->uuid, $locked));

        // Pero sigue siendo su dueño: la cadena de locks lo encuentra.
        $this->assertTrue($owner->acquireLockChain((string) $media->uuid)->isComplete());
    }

    public function test_a_restored_service_is_promoted_again_by_the_reconciliation(): void
    {
        $service = $this->service();
        $media = $this->save($service);
        $service->delete();

        // Restaurar no es operación de dominio en v1 (la policy lo prohíbe): se
        // hace por SQL administrativo y la reconciliación deja consistente el
        // estado dentro de su ventana.
        DB::table('frontend_services')->where('id', $service->getKey())->update(['deleted_at' => null]);

        $this->artisan('frontend:media:reconcile')->assertSuccessful();
        $this->promote($media->refresh());

        $this->assertTrue(app(MediaPromotionState::class)->isPromoted($media->refresh()));
    }

    // ------------------------------------------------------------- T3-14 ----

    public function test_the_render_falls_back_without_breaking_the_block(): void
    {
        $this->withoutRunningTheJob();
        $service = $this->service();

        // Pendiente de promoción.
        $this->save($service);
        app(FrontendCacheGeneration::class)->bump();
        $this->assertNull($this->renderedImageUrl($service->fresh()));

        // El caso del uuid inventado vive en su propio test: una violación de FK
        // aborta la transacción de PostgreSQL y haría fallar todo lo que sigue.

        // Sin imagen.
        DB::table('frontend_services')->where('id', $service->getKey())->update(['image_media_id' => null]);
        app(FrontendCacheGeneration::class)->bump();
        $this->assertNull($this->renderedImageUrl($service->fresh()));

        // En los tres casos la página sigue respondiendo con el servicio.
        $this->get('/servicios')->assertOk()->assertSee($service->title, escape: false);
    }

    public function test_an_invented_uuid_cannot_even_be_stored(): void
    {
        // Va en su propio test a propósito: una violación de constraint ABORTA la
        // transacción de PostgreSQL, así que cualquier aserción posterior en el
        // mismo test fallaría con 25P02 por una causa que no es la suya.
        $this->expectException(QueryException::class);

        DB::table('frontend_services')->where('id', $this->service()->getKey())
            ->update(['image_media_id' => '11111111-2222-4333-8444-555555555555']);
    }

    // ------------------------------------------------------------- T3-16 ----

    public function test_a_malformed_uuid_returns_false_instead_of_a_database_error(): void
    {
        // `media.uuid` es una columna uuid nativa: sin el guard de sintaxis esto
        // sería SQLSTATE 22P02, una excepción y no un «no encontrado».
        $owner = app(ServiceMediaReference::class);

        $this->assertFalse($owner->isReferencedByLiveContent('no-soy-un-uuid', $this->service()));
        $this->assertFalse($owner->acquireLockChain('no-soy-un-uuid')->isComplete());
    }

    // ------------------------------------------------------------- T3-18 ----

    public function test_the_service_strategy_is_registered(): void
    {
        $media = $this->attach($this->service());

        $this->assertInstanceOf(
            ServiceMediaReference::class,
            app(PromotableMediaOwners::class)->for($media),
        );
    }

    // -------------------------------------------------------------- T3-5 ----

    public function test_no_route_deletes_media_physically(): void
    {
        $service = $this->service();
        $primera = $this->save($service, 'primera.png');
        $this->promote($primera);
        $antes = Media::query()->count();

        $this->save($service->fresh(), 'segunda.png');
        $service->fresh()->delete();
        $this->artisan('frontend:media:reconcile')->assertSuccessful();

        $this->assertSame($antes + 1, Media::query()->count());
        $this->assertTrue(Storage::disk('public')->exists($primera->refresh()->getPathRelativeToRoot()));
    }
}
