<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Institutional page content (RFC-075, §16.1.1). A page owns editable sections
 * (draft) and a published snapshot.
 *
 * - `published_revision`: the JSON snapshot the PUBLIC render reads. Null until
 *   the first publish, when page(key) serves the §16.7 fallback.
 * - `draft_revision`: optimistic version bumped by every draft mutation; the
 *   publisher requires the expected value so a stale UI cannot overwrite a
 *   concurrent change.
 * - `revision`: publication counter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frontend_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 30)->unique();
            $table->boolean('is_enabled')->default(true);
            $table->jsonb('seo')->nullable();

            $table->jsonb('published_revision')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();

            $table->unsignedBigInteger('revision')->default(0);
            $table->unsignedBigInteger('draft_revision')->default(1);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frontend_pages');
    }
};
