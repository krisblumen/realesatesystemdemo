<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_zone', function (Blueprint $table): void {
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('zone_id')->constrained('zones')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['agent_id', 'zone_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_zone');
    }
};
