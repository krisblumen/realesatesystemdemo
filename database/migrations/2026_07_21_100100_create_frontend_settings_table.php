<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Site-wide singleton for the public frontend (RFC-071 + RFC-072 + RFC-073).
 *
 * The singleton is a PHYSICAL guarantee: UNIQUE on singleton_key collapses
 * concurrent inserts into one row, and the CHECK makes any value other than
 * 'default' impossible — an import or bug cannot create a second valid config.
 *
 * Brand media is referenced by EXPLICIT uuid columns (FK -> media.uuid): with
 * no physical media deletion in v1 collections accumulate versions, so
 * getFirstMedia() is not deterministic and is never used by the render.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frontend_settings', function (Blueprint $table) {
            $table->id();
            $table->string('singleton_key', 20)->default('default')->unique();

            // Identity (RFC-071).
            $table->string('site_name', 120);
            $table->string('tagline', 180)->nullable();
            $table->string('short_description', 300)->nullable();
            $table->string('legal_name')->nullable();

            // Contact (RFC-071).
            $table->string('public_phone', 30)->nullable();
            $table->string('whatsapp_phone', 30)->nullable();
            $table->string('public_email')->nullable();
            $table->string('public_address')->nullable();
            $table->jsonb('business_hours')->nullable();

            // SEO defaults (RFC-071).
            $table->string('default_meta_title')->nullable();
            $table->string('default_meta_description', 300)->nullable();
            $table->string('default_og_title')->nullable();
            $table->string('default_og_description', 300)->nullable();

            // Global CTAs — typed value objects {label,type,target} (RFC-073).
            $table->jsonb('primary_cta')->nullable();
            $table->jsonb('secondary_cta')->nullable();

            // Navigation — list of {key,label,enabled,sort_order,open_in_new_tab} (RFC-073).
            $table->jsonb('navigation')->nullable();

            // Footer — {columns:[{title,links:[{label,type,target,enabled}]}], legal_text} (RFC-073).
            $table->jsonb('footer')->nullable();

            // Runtime theme (RFC-072) and social links allowlist (RFC-071).
            $table->jsonb('theme')->nullable();
            $table->jsonb('social_links')->nullable();

            // Brand media by explicit uuid — the ONLY source of truth for the
            // active file. Collections are storage, not truth (§16.4).
            $table->uuid('logo_light_media_id')->nullable();
            $table->uuid('logo_dark_media_id')->nullable();
            $table->uuid('favicon_media_id')->nullable();
            $table->uuid('og_image_media_id')->nullable();

            $table->foreign('logo_light_media_id')->references('uuid')->on('media');
            $table->foreign('logo_dark_media_id')->references('uuid')->on('media');
            $table->foreign('favicon_media_id')->references('uuid')->on('media');
            $table->foreign('og_image_media_id')->references('uuid')->on('media');

            $table->timestamps();
        });

        // Physical singleton: UNIQUE alone allows a second row with another key;
        // the CHECK makes 'default' the only representable value.
        DB::statement(<<<'SQL'
            ALTER TABLE frontend_settings
            ADD CONSTRAINT frontend_settings_singleton_key_check
            CHECK (singleton_key = 'default')
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('frontend_settings');
    }
};
