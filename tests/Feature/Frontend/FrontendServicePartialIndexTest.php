<?php

namespace Tests\Feature\Frontend;

use App\Models\FrontendService;
use App\Models\ServiceType;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The uniqueness of service_type_code is a PARTIAL unique index over live rows
 * (§16.1.2): one active FrontendService per code, but a soft-deleted code can be
 * recreated. A permanent test so a future migration can't silently turn it into
 * a global UNIQUE that would forbid recreation.
 */
class FrontendServicePartialIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_partial_unique_index_exists_over_live_rows(): void
    {
        $definition = DB::selectOne(
            "select indexdef from pg_indexes where indexname = 'frontend_services_service_type_code_active_unique'"
        );

        $this->assertNotNull($definition, 'The named partial unique index must exist.');
        $this->assertStringContainsString('UNIQUE', $definition->indexdef);
        $this->assertStringContainsString('WHERE (deleted_at IS NULL)', $definition->indexdef);
    }

    public function test_two_live_rows_for_the_same_code_are_rejected(): void
    {
        // comercializacion already has a live FrontendService from the backfill.
        $this->expectException(UniqueConstraintViolationException::class);

        FrontendService::query()->create(['service_type_code' => 'comercializacion', 'title' => 'Dup']);
    }

    public function test_a_soft_deleted_code_can_be_recreated(): void
    {
        ServiceType::query()->firstOrCreate(['code' => 'temp'], ['label' => 'Temp', 'active' => true]);

        $first = FrontendService::query()->create(['service_type_code' => 'temp', 'title' => 'A']);
        $first->delete(); // soft delete

        $second = FrontendService::query()->create(['service_type_code' => 'temp', 'title' => 'B']);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSoftDeleted($first);
    }
}
