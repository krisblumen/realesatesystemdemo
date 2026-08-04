<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Draft editable sections of a page (RFC-075, §16.1.1). The owner edits these;
 * the render reads the page's published snapshot, never these rows directly.
 *
 * Both uniqueness rules are PARTIAL unique indexes over live rows (§16.1.2), not
 * Blueprint uniques: with SoftDeletes a global unique would keep a deleted
 * section's key/sort_order occupied forever, blocking reorder or recreation.
 * PostgreSQL cannot put a predicate on a UNIQUE constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frontend_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('frontend_page_id')->constrained('frontend_pages')->cascadeOnDelete();
            $table->string('section_key', 40);
            $table->string('type', 30);
            $table->jsonb('payload')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });

        // One live section per (page, key) and one live section per (page, order).
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX frontend_sections_page_section_key_active_unique
            ON frontend_sections (frontend_page_id, section_key)
            WHERE deleted_at IS NULL
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX frontend_sections_page_sort_order_active_unique
            ON frontend_sections (frontend_page_id, sort_order)
            WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('frontend_sections');
    }
};
