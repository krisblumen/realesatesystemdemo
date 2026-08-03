<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('title', 180);
            $table->string('slug', 200)->unique();
            $table->text('description')->nullable();
            $table->string('operation_type', 20);
            $table->string('property_type', 30);
            $table->string('status', 20)->default('borrador');
            $table->decimal('price', 14, 2);
            $table->unsignedSmallInteger('bedrooms')->nullable();
            $table->decimal('bathrooms', 3, 1)->nullable();
            $table->unsignedSmallInteger('parking_spaces')->nullable();
            $table->decimal('land_area', 10, 2)->nullable();
            $table->decimal('construction_area', 10, 2)->nullable();
            $table->foreignId('zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('meta_title', 180)->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->string('canonical_url', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'operation_type']);
            $table->index('property_type');
            $table->index('price');
            $table->index('zone_id');
            $table->index('agent_id');
        });

        DB::statement("ALTER TABLE properties ADD CONSTRAINT properties_operation_type_check CHECK (operation_type IN ('venta', 'renta'))");
        DB::statement("ALTER TABLE properties ADD CONSTRAINT properties_property_type_check CHECK (property_type IN ('casa', 'departamento', 'terreno', 'local', 'oficina', 'bodega'))");
        DB::statement("ALTER TABLE properties ADD CONSTRAINT properties_status_check CHECK (status IN ('borrador', 'publicado', 'pausado', 'vendido', 'rentado'))");
        DB::statement('ALTER TABLE properties ADD CONSTRAINT properties_price_positive CHECK (price > 0)');
        DB::statement('ALTER TABLE properties ADD CONSTRAINT properties_non_negative_metrics CHECK ((bedrooms IS NULL OR bedrooms >= 0) AND (bathrooms IS NULL OR bathrooms >= 0) AND (parking_spaces IS NULL OR parking_spaces >= 0) AND (land_area IS NULL OR land_area >= 0) AND (construction_area IS NULL OR construction_area >= 0))');
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
