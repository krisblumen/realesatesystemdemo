<?php

namespace Tests\Feature\Frontend;

use App\Models\FrontendSetting;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\UsesRealPostgresConnections;
use Tests\TestCase;

/**
 * T-2: the singleton is a PHYSICAL guarantee (CHECK + UNIQUE), not a convention.
 * A concurrent insert race must end with exactly one row, and no value other
 * than 'default' may ever exist.
 */
class FrontendSettingSingletonTest extends TestCase
{
    use RefreshDatabase, UsesRealPostgresConnections;

    public function test_two_overlapping_transactions_contend_and_leave_exactly_one_row(): void
    {
        $a = $this->realConnection('pgsql_singleton_a');
        $b = $this->realConnection('pgsql_singleton_b');

        $insert = "insert into frontend_settings (singleton_key, site_name, created_at, updated_at)
                   values ('default', 'New Hauz', now(), now())
                   on conflict (singleton_key) do nothing";

        try {
            // A inserts and HOLDS the transaction open: the unique index entry
            // is taken but not yet visible to anybody else.
            $a->beginTransaction();
            $a->statement($insert);

            // B overlaps A. PostgreSQL must make it WAIT on the unique index
            // until A resolves — that block is the proof of real contention,
            // which a sequential A-then-B call never exercises.
            $b->statement("set lock_timeout = '400ms'");
            $b->beginTransaction();

            $blocked = false;
            try {
                $b->statement($insert);
            } catch (QueryException $e) {
                $blocked = str_contains($e->getMessage(), 'lock timeout')
                    || str_contains($e->getMessage(), 'canceling statement');
                $b->rollBack();
            }

            $this->assertTrue($blocked, 'B must block on A: without contention this proves nothing.');

            // A wins the race.
            $a->commit();

            // B retries after the winner committed: the conflict is now visible,
            // so ON CONFLICT DO NOTHING inserts zero rows instead of throwing.
            $b->beginTransaction();
            $b->statement($insert);
            $b->commit();

            $this->assertSame(
                1,
                (int) $b->selectOne('select count(*) as c from frontend_settings')->c,
                'Physical singleton: contention must collapse into exactly one row.'
            );
        } finally {
            // These connections autocommit: undo what RefreshDatabase cannot roll back.
            $this->releaseRealConnections(['delete from frontend_settings']);
        }
    }

    public function test_check_constraint_rejects_any_singleton_key_other_than_default(): void
    {
        $this->expectException(QueryException::class);

        DB::statement("insert into frontend_settings (singleton_key, site_name, created_at, updated_at)
                       values ('secondary', 'Rogue', now(), now())");
    }

    public function test_current_creates_the_row_once_and_reuses_it(): void
    {
        $first = FrontendSetting::current();
        $second = FrontendSetting::current();

        $this->assertSame($first->id, $second->id);
        $this->assertSame('default', $first->singleton_key);
        $this->assertSame(1, FrontendSetting::count());
    }

    public function test_brand_media_ids_reject_a_uuid_that_does_not_exist_in_media(): void
    {
        $this->expectException(QueryException::class);

        $setting = FrontendSetting::current();
        $setting->logo_light_media_id = '00000000-0000-0000-0000-000000000001';
        $setting->save();
    }
}
