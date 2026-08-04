<?php

use App\Actions\Frontend\SeedInversionService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Editorial + availability layer for services (RFC-074, §16.6).
 *
 * `FrontendService` is 1:1 with `service_types.code` and carries the marketing
 * content plus the availability toggles. `ServiceType` stays the operational
 * source of truth (`active`); this table never grants a permission `active`
 * denies.
 *
 * The uniqueness of `service_type_code` is a PARTIAL unique index (only where
 * `deleted_at IS NULL`), not a Blueprint `->unique()`: with SoftDeletes a global
 * unique would forbid recreating the service of a soft-deleted code. PostgreSQL
 * cannot put a predicate on a UNIQUE constraint, so it must be an index
 * (§16.1.2). Named explicitly so rollback and schema asserts can reference it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frontend_services', function (Blueprint $table): void {
            $table->id();
            $table->string('service_type_code', 30);

            // Marketing content — "save is publishing" (Strategy A), so there is
            // a single payload, no draft/published split.
            $table->string('title', 120)->nullable();
            $table->string('short_description', 300)->nullable();
            $table->text('long_description')->nullable();
            $table->jsonb('bullets')->nullable();
            $table->string('icon', 60)->nullable();
            $table->string('image_alt', 255)->nullable();
            $table->uuid('image_media_id')->nullable();

            // Availability toggles (§16.6). Fail-closed: default false, and a
            // missing row means "not eligible" regardless.
            $table->boolean('show_in_home')->default(false);
            $table->boolean('show_in_services')->default(false);
            $table->boolean('allow_leads')->default(false);
            $table->unsignedInteger('sort_order')->default(0);

            $table->softDeletes();
            $table->timestamps();

            $table->foreign('service_type_code')->references('code')->on('service_types')->cascadeOnDelete();
            $table->foreign('image_media_id')->references('uuid')->on('media')->nullOnDelete();
        });

        // Partial unique index: one live FrontendService per code (§16.1.2).
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX frontend_services_service_type_code_active_unique
            ON frontend_services (service_type_code)
            WHERE deleted_at IS NULL
        SQL);

        // Backfill: every existing ServiceType gets a FrontendService so that
        // fail-closed eligibility (§16.6) does not silently break lead capture
        // for services that already accept leads. Content mirrors the current
        // hardcoded frontend so the site is unchanged. firstOrCreate keeps it
        // non-destructive if a row somehow already exists (M-5).
        $content = [
            'comercializacion' => [
                'title' => 'Comercialización',
                'short_description' => 'Vendemos y rentamos tu propiedad con estrategia, foto profesional y leads calificados.',
                'bullets' => ['Estrategia de venta', 'Fotografía profesional', 'Leads calificados'],
                'icon' => 'trending-up',
            ],
            'arquitectura' => [
                'title' => 'Arquitectura',
                'short_description' => 'Diseño a la medida que equilibra estética, función y valor a largo plazo.',
                'bullets' => ['Proyecto arquitectónico', 'Diseño a la medida', 'Valor a largo plazo'],
                'icon' => 'home',
            ],
            'construccion' => [
                'title' => 'Construcción',
                'short_description' => 'Ejecución de obra con control de calidad, tiempos y presupuesto.',
                'bullets' => ['Control de calidad', 'Tiempos y presupuesto', 'Construcción residencial'],
                'icon' => 'building',
            ],
        ];

        foreach (DB::table('service_types')->orderBy('sort_order')->get() as $type) {
            $defaults = $content[$type->code] ?? ['title' => $type->label];

            DB::table('frontend_services')->insertOrIgnore([
                'service_type_code' => $type->code,
                'title' => $defaults['title'] ?? $type->label,
                'short_description' => $defaults['short_description'] ?? null,
                'bullets' => isset($defaults['bullets']) ? json_encode($defaults['bullets']) : null,
                'icon' => $defaults['icon'] ?? null,
                'show_in_home' => true,
                'show_in_services' => true,
                // Everything that was a selectable lead service stays one.
                'allow_leads' => true,
                'sort_order' => $type->sort_order ?? 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Reconcile "Inversión inmobiliaria" (shown but never a ServiceType):
        // the idempotent, non-destructive action creates it as info-only
        // (allow_leads=false), never touching a customised row (B-5/M-5).
        app(SeedInversionService::class)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('frontend_services');
    }
};
