<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('zones', function (Blueprint $table): void {
            $table->foreignId('state_id')
                ->nullable()
                ->after('description')
                ->constrained('states')
                ->nullOnDelete();
            $table->foreignId('municipality_id')
                ->nullable()
                ->after('state_id')
                ->constrained('municipalities')
                ->nullOnDelete();
            $table->string('postal_code', 10)->nullable()->after('municipality_id');

            $table->index('state_id');
            $table->index('municipality_id');
        });

        if (Schema::hasColumn('zones', 'municipality')) {
            Schema::table('zones', function (Blueprint $table): void {
                $table->dropColumn('municipality');
            });
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');

            if (! Schema::hasColumn('zones', 'polygon')) {
                DB::statement('ALTER TABLE zones ADD COLUMN polygon geometry(Polygon,4326)');
            }

            if (! Schema::hasColumn('zones', 'center_point')) {
                DB::statement('ALTER TABLE zones ADD COLUMN center_point geometry(Point,4326) NULL');
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('zones', function (Blueprint $table): void {
            $table->dropIndex(['municipality_id']);
            $table->dropIndex(['state_id']);
            $table->dropConstrainedForeignId('municipality_id');
            $table->dropConstrainedForeignId('state_id');
            $table->dropColumn('postal_code');
            $table->string('municipality')->nullable();
        });
    }
};
