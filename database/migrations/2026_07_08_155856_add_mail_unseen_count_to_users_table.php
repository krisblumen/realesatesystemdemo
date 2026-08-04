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
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('mail_unseen_count')->nullable()->after('last_login_at');
            $table->timestamp('mail_unseen_synced_at')->nullable()->after('mail_unseen_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['mail_unseen_count', 'mail_unseen_synced_at']);
        });
    }
};
