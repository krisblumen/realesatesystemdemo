<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_assignment_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('from_agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 30)->default('automatic');
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index('lead_id');
            $table->index('to_agent_id');
            $table->index('assigned_by_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_assignment_logs');
    }
};
