<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lona_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            // Nullable: la asignación inicial la hace owner/admin sin solicitud previa.
            $table->foreignId('lona_request_id')->nullable()->constrained('lona_requests')->nullOnDelete();
            $table->string('operation_type'); // App\Enums\OperationType (venta/renta)
            $table->unsignedInteger('cantidad');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index('agent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lona_batches');
    }
};
