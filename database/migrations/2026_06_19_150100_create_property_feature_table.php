<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_feature', function (Blueprint $table) {
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained('features')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['property_id', 'feature_id']);
            $table->index(
                ['feature_id', 'property_id'],
                'property_feature_feature_property_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_feature');
    }
};
