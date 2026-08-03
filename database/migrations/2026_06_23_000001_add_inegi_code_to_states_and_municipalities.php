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
        Schema::table('states', function (Blueprint $table): void {
            $table->string('inegi_code', 2)->nullable()->unique()->after('clave');
        });

        Schema::table('municipalities', function (Blueprint $table): void {
            $table->string('inegi_code', 3)->nullable()->after('clave');
            $table->unique(['state_id', 'inegi_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('states', function (Blueprint $table): void {
            $table->dropUnique(['inegi_code']);
            $table->dropColumn('inegi_code');
        });

        Schema::table('municipalities', function (Blueprint $table): void {
            $table->dropUnique(['state_id', 'inegi_code']);
            $table->dropColumn('inegi_code');
        });
    }
};
