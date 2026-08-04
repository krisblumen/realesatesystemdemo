<?php

namespace Tests\Feature\Frontend;

use App\Jobs\PromoteFrontendMedia;
use App\Models\FrontendPage;
use App\Models\FrontendSection;
use App\Models\FrontendService;
use App\Models\FrontendSetting;
use App\Models\User;
use App\Services\Frontend\FrontendPagePublisher;
use App\Services\Frontend\PublishedMediaReference;
use Database\Seeders\PermissionSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

/**
 * Épica 12.1, Lote A — TA-5 a TA-8, TA-12, TA-13: the promotion state machine.
 *
 * Promotion is not "copy some bytes": `Media::getUrl()` resolves against the disk
 * stored ON THE ROW, so the row is what has to change for a published image to
 * become reachable. And because the flags live in a shared JSON column written by
 * both the publisher and the job, the states have invariants that must hold under
 * republish, cancellation and retry:
 *
 *   1. promoted ⇒ no pending_promotion
 *   2. pending_promotion ⇒ referenced by the live published revision
 *   3. promoted is TERMINAL
 */
class FrontendMediaPromotionTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('frontend-private');
        $this->seed(PermissionSeeder::class);
        $this->owner = User::factory()->withRole('owner')->create();
    }

    private function page(string $key = 'home'): FrontendPage
    {
        return FrontendPage::query()->where('key', $key)->firstOrFail();
    }

    private function hero(string $key = 'home'): FrontendSection
    {
        return $this->page($key)->sections()->where('section_key', 'hero')->firstOrFail();
    }

    private function attachSlide(?FrontendSection $section = null): Media
    {
        return ($section ?? $this->hero())
            ->addMedia(UploadedFile::fake()->image('slide.png', 1200, 675))
            ->toMediaCollection('images');
    }

    /** @param  list<string>  $uuids */
    private function publishHeroWith(array $uuids, string $key = 'home'): void
    {
        $slides = [];
        foreach (array_values($uuids) as $i => $uuid) {
            $slides[] = ['media_id' => $uuid, 'alt' => null, 'decorative' => true, 'sort_order' => $i];
        }

        $this->hero($key)->update(['payload' => ['title' => 'Hero de prueba', 'slides' => $slides]]);

        $page = $this->page($key)->fresh();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, $this->owner);
    }

    private function runJob(string $uuid): void
    {
        app()->call([new PromoteFrontendMedia($uuid), 'handle']);
    }

    // ------------------------------------------------------------- TA-5 -----

    public function test_promotion_flips_the_row_to_the_public_disk_and_keeps_the_private_copy(): void
    {
        $media = $this->attachSlide();
        $relative = $media->getPathRelativeToRoot();

        $this->publishHeroWith([(string) $media->uuid]); // queue is sync: the job runs

        $media->refresh();

        // The ROW is what makes the URL public — a flag alone would not.
        $this->assertSame('public', $media->disk);
        $this->assertSame('public', $media->conversions_disk);
        $this->assertTrue($media->getCustomProperty(PublishedMediaReference::PROMOTED));
        $this->assertFalse($media->hasCustomProperty(PublishedMediaReference::PENDING), 'Invariant 1: promoted ⇒ no pending.');

        $this->assertTrue(Storage::disk('public')->exists($relative), 'The original must land on the public disk.');
        $this->assertStringNotContainsString('frontend-private', $media->getUrl());

        // v1 never deletes: the private copy survives and is inventoried by
        // frontend:media:report-unreferenced.
        $this->assertTrue(
            Storage::disk('frontend-private')->exists($relative),
            'The private copy must NOT be deleted (§18.13).'
        );
    }

    public function test_promotion_is_idempotent(): void
    {
        $media = $this->attachSlide();
        $this->publishHeroWith([(string) $media->uuid]);

        $relative = $media->refresh()->getPathRelativeToRoot();
        $sizeAfterFirst = Storage::disk('public')->size($relative);

        // A retry must not duplicate the file nor change the state.
        $this->runJob((string) $media->uuid);
        $this->runJob((string) $media->uuid);

        $media->refresh();
        $this->assertSame('public', $media->disk);
        $this->assertSame($sizeAfterFirst, Storage::disk('public')->size($relative));
        $this->assertTrue($media->getCustomProperty(PublishedMediaReference::PROMOTED));
    }

    // ------------------------------------------------------------- TA-6 -----

    public function test_a_rolled_back_publish_leaves_no_pending_flag(): void
    {
        Queue::fake();

        $media = $this->attachSlide();

        // The publish writes `pending_promotion` inside its own transaction. If
        // the surrounding work rolls back, the snapshot and the flag disappear
        // together — the promotion can never outlive the publish that ordered it.
        try {
            DB::transaction(function () use ($media): void {
                $this->publishHeroWith([(string) $media->uuid]);

                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $media->refresh();
        $this->assertFalse($media->hasCustomProperty(PublishedMediaReference::PENDING));
        $this->assertNull($this->page()->fresh()->published_revision);
    }

    // ------------------------------------------------------------- TA-7 -----

    public function test_republishing_already_promoted_media_writes_no_pending_flag(): void
    {
        $media = $this->attachSlide();
        $this->publishHeroWith([(string) $media->uuid]);

        $this->assertTrue($media->refresh()->getCustomProperty(PublishedMediaReference::PROMOTED));

        // Publishing again while the same media is still referenced must be a
        // no-op for its state: `promoted` is terminal.
        $this->publishHeroWith([(string) $media->uuid]);

        $media->refresh();
        $this->assertTrue($media->getCustomProperty(PublishedMediaReference::PROMOTED));
        $this->assertFalse(
            $media->hasCustomProperty(PublishedMediaReference::PENDING),
            'Republishing promoted media must not resurrect a pending flag (M-1).'
        );
    }

    public function test_the_job_cleans_a_residual_pending_flag_on_promoted_media(): void
    {
        $media = $this->attachSlide();
        $this->publishHeroWith([(string) $media->uuid]);

        // Simulate a residue from any other path (legacy row, interrupted retry).
        $media->refresh()->setCustomProperty(PublishedMediaReference::PENDING, true)->save();

        $this->runJob((string) $media->uuid);

        $this->assertFalse(
            $media->refresh()->hasCustomProperty(PublishedMediaReference::PENDING),
            'The job must restore invariant 1 on its early exit.'
        );
    }

    // ------------------------------------------------------------- TA-8 -----

    public function test_a_publish_that_drops_a_reference_cancels_its_pending_promotion(): void
    {
        Queue::fake(); // hold the job so the reference can be dropped first

        $dropped = $this->attachSlide();
        $kept = $this->attachSlide();

        $this->publishHeroWith([(string) $dropped->uuid]);
        $this->assertTrue($dropped->refresh()->getCustomProperty(PublishedMediaReference::PENDING));

        // A second publish replaces the slide BEFORE the job ran.
        $this->publishHeroWith([(string) $kept->uuid]);

        // The publisher cancelled it deterministically: pending → draft.
        $dropped->refresh();
        $this->assertFalse($dropped->hasCustomProperty(PublishedMediaReference::PENDING), 'Invariant 2: pending ⇒ still referenced.');
        $this->assertFalse($dropped->hasCustomProperty(PublishedMediaReference::PROMOTED));

        // And the job that was already queued must NOT promote it when it runs.
        $this->runJob((string) $dropped->uuid);

        $dropped->refresh();
        $this->assertSame('frontend-private', $dropped->disk, 'A dereferenced media must never become public.');
        $this->assertFalse($dropped->hasCustomProperty(PublishedMediaReference::PROMOTED));

        // The file is untouched on the private disk — nothing is ever deleted.
        $this->assertTrue(Storage::disk('frontend-private')->exists($dropped->getPathRelativeToRoot()));
    }

    // ------------------------------------------------- hero-logo-propio -----

    public function test_the_hero_own_logo_is_found_promoted_and_never_reported_as_orphaned(): void
    {
        // El logo propio del hero (cambio cms-pagina-proyectos) usa la MISMA
        // clave `media_id` que cualquier otra imagen del payload (D4):
        // mediaIds() lo encuentra recorriendo el árbol, sin rama de código
        // nueva — esta prueba confirma que la promoción y el reporte de
        // huérfanas lo tratan exactamente igual que a una slide.
        $media = $this->attachSlide();

        $this->hero()->update(['payload' => [
            'title' => 'Hero de prueba',
            'logo_enabled' => true,
            'logo' => ['media_id' => (string) $media->uuid, 'alt' => 'Logo propio'],
        ]]);

        $page = $this->page()->fresh();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, $this->owner);

        $media->refresh();
        $this->assertSame('public', $media->disk, 'El logo propio del hero debe promoverse como cualquier media referenciada.');
        $this->assertTrue($media->getCustomProperty(PublishedMediaReference::PROMOTED));

        $this->assertContains(
            (string) $media->uuid,
            app(PublishedMediaReference::class)->mediaIdsOf($page->fresh()),
            'hero.logo.media_id debe quedar referenciado por el snapshot publicado.',
        );

        $this->artisan('frontend:media:report-unreferenced')
            ->expectsOutputToContain('No unreferenced frontend media.')
            ->assertSuccessful();
    }

    public function test_reconciliation_clears_a_pending_flag_nobody_references(): void
    {
        Queue::fake();

        $orphan = $this->attachSlide();
        $this->publishHeroWith([(string) $orphan->uuid]);

        // Drop the reference WITHOUT going through the publisher, so the
        // cancellation never ran: exactly the state a crashed process leaves.
        $this->hero()->update(['payload' => ['title' => 'Hero de prueba', 'slides' => []]]);
        $page = $this->page()->fresh();
        app(FrontendPagePublisher::class)->publish($page, $page->draft_revision, $this->owner);
        $orphan->refresh()->setCustomProperty(PublishedMediaReference::PENDING, true)->save();

        $this->artisan('frontend:media:reconcile')->assertSuccessful();

        $this->assertFalse(
            $orphan->refresh()->hasCustomProperty(PublishedMediaReference::PENDING),
            'The cleanup sweep must return an unreferenced pending media to draft.'
        );
    }

    // ------------------------------------------------------------ TA-12 -----

    public function test_reconciliation_never_touches_media_outside_its_scope(): void
    {
        // CAMBIO INTENCIONAL de la Épica 12.3 §9.2. Este test protegía dos cosas
        // a la vez y sólo una sigue vigente:
        //
        // - `FrontendService.image` ENTRA en alcance con 12.3, así que su flag
        //   colgado ahora SÍ debe limpiarse. La aserción se invierte.
        // - `FrontendSetting` sigue afuera, y ESA es la regresión que este test
        //   protege de verdad. Se conserva intacta.
        //
        // Borrarlo sin reemplazo habría perdido la segunda; dejarlo como estaba
        // habría creado una contradicción falsa con el alcance nuevo.
        $service = FrontendService::query()->firstOrFail();
        $serviceMedia = $service->addMedia(UploadedFile::fake()->image('svc.png'))->toMediaCollection('image');
        $serviceMedia->setCustomProperty(PublishedMediaReference::PENDING, true)->save();

        $brand = FrontendSetting::current()
            ->addMedia(UploadedFile::fake()->image('logo.png'))->toMediaCollection('logo-light');
        $brand->setCustomProperty(PublishedMediaReference::PENDING, true)->save();

        $this->artisan('frontend:media:reconcile')->assertSuccessful();

        // Ninguna columna viva referencia esta media: su `pending` estaba colgado
        // y la reconciliación lo limpia.
        $this->assertNull($serviceMedia->refresh()->getCustomProperty(PublishedMediaReference::PENDING));

        // La media de marca no tiene estrategia declarada — fail-closed — así que
        // el comando ni la lee ni la reescribe.
        $this->assertTrue($brand->refresh()->getCustomProperty(PublishedMediaReference::PENDING));
    }

    public function test_the_scheduler_registers_no_command_that_deletes_media(): void
    {
        $commands = collect(app(Schedule::class)->events())->map(fn ($e) => $e->command ?? '')->implode(' ');

        $this->assertStringContainsString('frontend:media:reconcile', $commands);

        foreach (['prune', 'purge', 'media:delete', 'maintain-media'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $commands, "No scheduled command may delete media (§16.4): {$forbidden}.");
        }
    }

    // ------------------------------------------------------------ TA-13 -----

    public function test_dropping_or_reordering_slides_never_deletes_media(): void
    {
        $a = $this->attachSlide();
        $b = $this->attachSlide();

        $this->publishHeroWith([(string) $a->uuid, (string) $b->uuid]);

        $relativeA = $a->refresh()->getPathRelativeToRoot();

        // Reorder, then drop one entirely.
        $this->publishHeroWith([(string) $b->uuid, (string) $a->uuid]);
        $this->publishHeroWith([(string) $b->uuid]);

        // The row and the file both survive: removing a slide un-references a
        // file, it never destroys it.
        $this->assertNotNull(Media::query()->where('uuid', $a->uuid)->first(), 'The media row must survive.');
        $this->assertTrue(
            Storage::disk('public')->exists($relativeA) || Storage::disk('frontend-private')->exists($relativeA),
            'The file must survive on whichever disk it had reached.'
        );
    }
}
