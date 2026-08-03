<?php

namespace Tests\Feature\Frontend;

use App\Jobs\PromoteFrontendMedia;
use App\Models\FrontendPage;
use App\Models\User;
use App\Services\Frontend\FrontendPagePublisher;
use App\Services\Frontend\PublishedMediaReference;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\UsesRealPostgresConnections;
use Tests\TestCase;

/**
 * Épica 12.1, Lote A — TA-9: the publisher ↔ job race, on REAL concurrent
 * PostgreSQL connections (§16.11 — a sequential call proves nothing here).
 *
 * The design claim under test is M-3: the job must not promote media that a
 * concurrent publish has just dereferenced. Holding only the media row lock was
 * not enough — the job could read a `published_revision` that still named the
 * file, start copying, and have a publish drop the reference meanwhile. Taking
 * the PAGE lock first is what makes «check the reference» and «write the state»
 * one atomic decision, and it is the same global order the publisher uses
 * (page → sections → media), so the two can never deadlock.
 */
class FrontendMediaPromotionConcurrencyTest extends TestCase
{
    use RefreshDatabase, UsesRealPostgresConnections;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('frontend-private');
        $this->seed(PermissionSeeder::class);
    }

    public function test_the_promotion_job_waits_for_a_held_page_lock(): void
    {
        $page = FrontendPage::query()->where('key', 'home')->firstOrFail();
        $hero = $page->sections()->where('section_key', 'hero')->firstOrFail();

        $media = $hero->addMedia(UploadedFile::fake()->image('slide.png', 1200, 675))
            ->toMediaCollection('images');

        $hero->update(['payload' => [
            'title' => 'Hero de prueba',
            'slides' => [['media_id' => (string) $media->uuid, 'alt' => null, 'decorative' => true, 'sort_order' => 0]],
        ]]);

        // A publisher on ANOTHER connection is mid-transaction, holding the page
        // row. `frontend_pages` is seeded by migration, so it is committed and
        // visible to this independent session.
        $publisher = $this->realConnection('pgsql_promo_publisher');

        try {
            $publisher->statement("set lock_timeout = '2s'");
            $publisher->beginTransaction();
            $publisher->statement("select id from frontend_pages where id = {$page->getKey()} for update");

            // The job runs here and must BLOCK on that same row. Without the page
            // lock it would sail through and promote — which is exactly the race
            // M-3 describes. lock_timeout turns the wait into an observable error.
            DB::statement("set lock_timeout = '500ms'");

            $blocked = false;
            try {
                app()->call([new PromoteFrontendMedia((string) $media->uuid), 'handle']);
            } catch (QueryException $e) {
                $blocked = str_contains($e->getMessage(), 'lock timeout')
                    || str_contains($e->getMessage(), 'canceling statement');
            } finally {
                DB::statement('set lock_timeout = 0');
            }

            $this->assertTrue($blocked, 'The job must contend for the page lock; without contention this proves nothing.');

            // It blocked BEFORE touching anything: no half-promotion.
            $media->refresh();
            $this->assertSame('frontend-private', $media->disk);
            $this->assertFalse($media->getCustomProperty(PublishedMediaReference::PROMOTED) === true);

            $publisher->commit();
        } finally {
            $this->releaseRealConnections();
        }
    }

    public function test_the_promotion_job_also_waits_for_a_held_section_lock(): void
    {
        $page = FrontendPage::query()->where('key', 'home')->firstOrFail();
        $hero = $page->sections()->where('section_key', 'hero')->firstOrFail();

        // NOTE: the section row is deliberately NOT written from this connection.
        // RefreshDatabase keeps the test inside an uncommitted transaction, so an
        // update here would hold that row and the holder below would wait on US,
        // forever. `addMedia` only writes the media row, never the section.
        $media = $hero->addMedia(UploadedFile::fake()->image('slide.png', 1200, 675))
            ->toMediaCollection('images');

        // Only the SECTION row is held. The page is free, so the job gets past
        // link 1 and must stop at link 2 — which is what proves the section is
        // in the chain and comes AFTER the page. The Lote A audit found this job
        // taking media before the section; that inversion would still have
        // blocked here, so this test is paired with the static order check below.
        $holder = $this->realConnection('pgsql_section_holder');

        try {
            // Safety net: if a future change makes this connection contend with
            // the test's own transaction, it fails fast instead of hanging the suite.
            $holder->statement("set lock_timeout = '2s'");
            $holder->beginTransaction();
            $holder->statement("select id from frontend_sections where id = {$hero->getKey()} for update");

            DB::statement("set lock_timeout = '500ms'");

            $blocked = false;
            try {
                app()->call([new PromoteFrontendMedia((string) $media->uuid), 'handle']);
            } catch (QueryException $e) {
                $blocked = str_contains($e->getMessage(), 'lock timeout')
                    || str_contains($e->getMessage(), 'canceling statement');
            } finally {
                DB::statement('set lock_timeout = 0');
            }

            $this->assertTrue($blocked, 'The job must take the section lock too.');
            $this->assertSame('frontend-private', $media->refresh()->disk);

            $holder->commit();
        } finally {
            $this->releaseRealConnections();
        }
    }

    public function test_the_real_publisher_contends_on_the_same_head_of_the_chain(): void
    {
        $owner = User::factory()->withRole('owner')->create();
        $page = FrontendPage::query()->where('key', 'home')->firstOrFail();
        $hero = $page->sections()->where('section_key', 'hero')->firstOrFail();
        $hero->update(['payload' => ['title' => 'Hero de prueba']]);

        // Not only the job: the REAL publisher starts at the same page row, which
        // is what makes the two orders one order.
        $holder = $this->realConnection('pgsql_publisher_holder');

        try {
            $holder->statement("set lock_timeout = '2s'");
            $holder->beginTransaction();
            $holder->statement("select id from frontend_pages where id = {$page->getKey()} for update");

            DB::statement("set lock_timeout = '500ms'");

            $blocked = false;
            try {
                app(FrontendPagePublisher::class)->publish($page->fresh(), $page->fresh()->draft_revision, $owner);
            } catch (QueryException $e) {
                $blocked = str_contains($e->getMessage(), 'lock timeout')
                    || str_contains($e->getMessage(), 'canceling statement');
            } finally {
                DB::statement('set lock_timeout = 0');
            }

            $this->assertTrue($blocked, 'The publisher must contend on the page row as well.');
            $this->assertNull($page->fresh()->published_revision, 'A blocked publish must not write a snapshot.');

            $holder->commit();
        } finally {
            $this->releaseRealConnections();
        }
    }

    public function test_the_shared_lock_chain_takes_page_then_section_then_media(): void
    {
        // A static guard on the ONE routine every single-media actor uses. The
        // Lote A audit found the job doing `page → media → section` while its
        // comment claimed otherwise: contention tests alone did not catch the
        // inversion, because a wrong order still blocks. This asserts the order
        // itself, cheaply, and covers job and reconciliation at once.
        $source = file_get_contents(app_path('Services/Frontend/PublishedMediaReference.php'));
        $chain = substr($source, strpos($source, 'public function lockChainFor'));
        $chain = substr($chain, 0, strpos($chain, "\n    }"));

        $page = strpos($chain, 'FrontendPage::query()->whereKey($page->getKey())->lockForUpdate()');
        $section = strpos($chain, 'FrontendSection::withTrashed()->whereKey($section->getKey())->lockForUpdate()');
        $media = strpos($chain, "Media::query()->where('uuid', \$uuid)->lockForUpdate()");

        $this->assertNotFalse($page, 'The chain must lock the page.');
        $this->assertNotFalse($section, 'The chain must lock the section.');
        $this->assertNotFalse($media, 'The chain must lock the media.');

        $this->assertLessThan($section, $page, 'The page lock must come before the section lock.');
        $this->assertLessThan($media, $section, 'The section lock must come before the media lock.');
    }

    public function test_publisher_and_job_serialize_on_one_shared_lock_order(): void
    {
        $pageId = FrontendPage::query()->where('key', 'home')->value('id');

        $a = $this->realConnection('pgsql_order_a');
        $b = $this->realConnection('pgsql_order_b');

        // Both actors start at the SAME head of the order (the page row). That is
        // what guarantees no cycle: nobody reaches `media` before the page.
        $lock = "select id from frontend_pages where id = {$pageId} for update";

        try {
            $a->beginTransaction();
            $a->statement($lock);

            $b->statement("set lock_timeout = '400ms'");
            $b->beginTransaction();

            $blocked = false;
            try {
                $b->statement($lock);
            } catch (QueryException $e) {
                $blocked = str_contains($e->getMessage(), 'lock timeout')
                    || str_contains($e->getMessage(), 'canceling statement');
                $b->rollBack();
            }

            $this->assertTrue($blocked, 'The second actor must wait on the page row.');

            $a->commit();

            // Once released, the second acquires cleanly — serialized, not deadlocked.
            $b->beginTransaction();
            $b->statement($lock);
            $b->commit();
        } finally {
            $this->releaseRealConnections();
        }
    }

    public function test_a_job_queued_before_a_dereferencing_publish_does_not_promote(): void
    {
        $owner = User::factory()->withRole('owner')->create();
        $page = FrontendPage::query()->where('key', 'home')->firstOrFail();
        $hero = $page->sections()->where('section_key', 'hero')->firstOrFail();

        $dropped = $hero->addMedia(UploadedFile::fake()->image('a.png', 1200, 675))->toMediaCollection('images');
        $kept = $hero->addMedia(UploadedFile::fake()->image('b.png', 1200, 675))->toMediaCollection('images');

        $slide = fn (string $uuid): array => [
            'title' => 'Hero de prueba',
            'slides' => [['media_id' => $uuid, 'alt' => null, 'decorative' => true, 'sort_order' => 0]],
        ];

        $publisher = app(FrontendPagePublisher::class);

        Queue::fake();

        $hero->update(['payload' => $slide((string) $dropped->uuid)]);
        $publisher->publish($page->fresh(), $page->fresh()->draft_revision, $owner);

        // The publish that drops the reference lands BEFORE the queued job runs.
        $hero->update(['payload' => $slide((string) $kept->uuid)]);
        $publisher->publish($page->fresh(), $page->fresh()->draft_revision, $owner);

        // Now the stale job executes. Under the page lock it re-reads the live
        // revision, finds the media dereferenced, and stops without copying.
        app()->call([new PromoteFrontendMedia((string) $dropped->uuid), 'handle']);

        $dropped->refresh();
        $this->assertSame('frontend-private', $dropped->disk, 'A dereferenced media must never reach the public disk.');
        $this->assertFalse($dropped->hasCustomProperty(PublishedMediaReference::PROMOTED));
        $this->assertFalse($dropped->hasCustomProperty(PublishedMediaReference::PENDING));
        $this->assertFalse(
            Storage::disk('public')->exists($dropped->getPathRelativeToRoot()),
            'No public bytes for a media the live revision does not reference.'
        );
    }
}
