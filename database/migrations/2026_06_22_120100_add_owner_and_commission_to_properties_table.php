<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->foreignId('owner_id')
                ->nullable()
                ->after('agent_id')
                ->constrained('property_owners')
                ->nullOnDelete();
            $table->decimal('commission_percentage', 5, 2)->nullable()->after('owner_id');
            $table->index('owner_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE properties ADD CONSTRAINT properties_commission_range CHECK (commission_percentage IS NULL OR (commission_percentage >= 0 AND commission_percentage <= 100))');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE properties DROP CONSTRAINT IF EXISTS properties_commission_range');
        }

        Schema::table('properties', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_id');
            $table->dropColumn('commission_percentage');
        });
    }
};
