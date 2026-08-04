<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('postal_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('postal_code', 5);
            $table->string('colonia');
            $table->foreignId('municipality_id')
                ->nullable()
                ->constrained('municipalities')
                ->nullOnDelete();
            $table->foreignId('state_id')
                ->nullable()
                ->constrained('states')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['postal_code', 'colonia']);
            $table->index('postal_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('postal_codes');
    }
};
