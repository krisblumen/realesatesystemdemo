<?php

namespace Tests\Feature\Frontend;

use App\Services\Frontend\FrontendCacheGeneration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\UsesRealPostgresConnections;
use Tests\TestCase;

/**
 * T-10a: the cache generation is DURABLE and its bump is a single atomic SQL
 * statement. Cache::increment() is banned here: on the database store it
 * returns false when the key does not exist yet (DatabaseStore.php:273-286),
 * so a naive or concurrent initialization silently loses bumps.
 */
class FrontendCacheGenerationTest extends TestCase
{
    use RefreshDatabase, UsesRealPostgresConnections;

    public function test_migration_initializes_exactly_one_row_with_generation_one(): void
    {
        $rows = DB::select('select id, generation from frontend_cache_generation');

        $this->assertCount(1, $rows);
        $this->assertSame(1, (int) $rows[0]->id);
        $this->assertSame(1, (int) $rows[0]->generation);
    }

    public function test_check_constraint_rejects_a_second_row(): void
    {
        $this->expectException(QueryException::class);

        DB::statement('insert into frontend_cache_generation (id, generation) values (2, 1)');
    }

    public function test_bump_returns_the_new_generation_atomically(): void
    {
        $service = app(FrontendCacheGeneration::class);

        $this->assertSame(2, $service->bump());
        $this->assertSame(3, $service->bump());
        $this->assertSame(3, $service->current());
    }

    public function test_two_overlapping_bumps_contend_and_neither_is_lost(): void
    {
        // Two REAL PostgreSQL connections with OVERLAPPING transactions: this is
        // the scenario a read-modify-write in PHP (or Cache::increment) loses.
        $a = $this->realConnection('pgsql_generation_a');
        $b = $this->realConnection('pgsql_generation_b');

        $bump = 'update frontend_cache_generation set generation = generation + 1 where id = 1 returning generation';

        try {
            // A bumps and HOLDS the row lock open.
            $a->beginTransaction();
            $first = (int) $a->selectOne($bump)->generation;
            $this->assertSame(2, $first);

            // B overlaps A and must WAIT on the row lock. That block is what
            // proves contention — sequential calls would just pass trivially.
            $b->statement("set lock_timeout = '400ms'");
            $b->beginTransaction();

            $blocked = false;
            try {
                $b->selectOne($bump);
            } catch (QueryException $e) {
                $blocked = str_contains($e->getMessage(), 'lock timeout')
                    || str_contains($e->getMessage(), 'canceling statement');
                $b->rollBack();
            }

            $this->assertTrue($blocked, 'B must block on A: without contention this proves nothing.');

            $a->commit();

            // B retries after A committed. Because the increment is computed by
            // the engine (not read-modify-write in PHP), B sees 2 and lands 3:
            // A's bump is NOT lost.
            $b->beginTransaction();
            $second = (int) $b->selectOne($bump)->generation;
            $b->commit();

            $this->assertSame(3, $second, 'Neither bump may be lost under real contention.');
        } finally {
            // These connections autocommit: restore what RefreshDatabase cannot roll back.
            $this->releaseRealConnections(['update frontend_cache_generation set generation = 1 where id = 1']);
        }
    }
}
