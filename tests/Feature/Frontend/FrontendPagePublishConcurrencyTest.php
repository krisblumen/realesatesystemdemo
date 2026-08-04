<?php

namespace Tests\Feature\Frontend;

use App\Models\FrontendPage;
use App\Models\User;
use App\Services\Frontend\FrontendPageContentService;
use App\Services\Frontend\FrontendPagePublisher;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\UsesRealPostgresConnections;
use Tests\TestCase;

/**
 * The publish/edit race on real PostgreSQL connections (RFC-075).
 *
 * The content service and the publisher lock `frontend_pages` FOR UPDATE in the
 * same deterministic order, so a concurrent edit and a publish serialize on that
 * row lock. And the optimistic draft_revision check means a publish that loaded
 * an old revision loses to an edit that committed first — the snapshot is never
 * overwritten from a stale UI.
 */
class FrontendPagePublishConcurrencyTest extends TestCase
{
    use RefreshDatabase, UsesRealPostgresConnections;

    public function test_a_second_transaction_blocks_on_the_page_row_lock(): void
    {
        $pageId = FrontendPage::query()->where('key', 'home')->value('id');

        $a = $this->realConnection('pgsql_page_a');
        $b = $this->realConnection('pgsql_page_b');

        $lock = "select id from frontend_pages where id = {$pageId} for update";

        try {
            // A takes the page row lock and HOLDS it — this is what the content
            // service and publisher do first inside their transactions.
            $a->beginTransaction();
            $a->statement($lock);

            // B overlaps: it must WAIT on the same row lock, proving the two
            // paths cannot mutate the page concurrently.
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

            $this->assertTrue($blocked, 'B must block on A: without contention this proves nothing.');

            $a->commit();

            // Once A releases, B acquires the lock cleanly.
            $b->beginTransaction();
            $b->statement($lock);
            $b->commit();
        } finally {
            $this->releaseRealConnections();
        }
    }

    public function test_an_edit_that_commits_first_makes_a_stale_publish_conflict(): void
    {
        $this->seed(PermissionSeeder::class);
        $owner = User::factory()->withRole('owner')->create();

        $page = FrontendPage::query()->where('key', 'home')->firstOrFail();
        $staleRevision = $page->draft_revision;

        // A concurrent editor commits a draft change, bumping the revision.
        $section = $page->sections()->where('section_key', 'hero')->firstOrFail();
        app(FrontendPageContentService::class)->updateSectionPayload($section, ['title' => 'Editado en paralelo']);

        // Publishing with the revision the UI had loaded must now conflict.
        $this->expectException(ValidationException::class);
        app(FrontendPagePublisher::class)->publish($page->fresh(), $staleRevision, $owner);
    }
}
