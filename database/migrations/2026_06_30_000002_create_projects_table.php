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
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 180);
            $table->string('slug')->unique();
            $table->string('project_type', 40)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('project_type')
                ->references('code')
                ->on('project_types')
                ->nullOnDelete();
            $table->index('project_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
