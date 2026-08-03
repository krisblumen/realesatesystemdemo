<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->text('message')->nullable();
            $table->string('source', 20)->default('web');
            $table->string('status', 30)->default('nuevo');
            $table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('agent_id');
            $table->index(['agent_id', 'assigned_at']);
            $table->index('property_id');
            $table->index('zone_id');
        });

        DB::statement("ALTER TABLE leads ADD CONSTRAINT leads_source_check CHECK (source IN ('web', 'landing', 'inmueble', 'manual', 'telefono'))");
        DB::statement("ALTER TABLE leads ADD CONSTRAINT leads_status_check CHECK (status IN ('nuevo', 'contactado', 'en_seguimiento', 'cerrado_ganado', 'cerrado_perdido'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
