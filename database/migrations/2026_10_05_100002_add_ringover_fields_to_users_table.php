<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('ringover_user_id', 64)->nullable()->unique()->after('team_id');
            $table->string('ringover_number', 20)->nullable()->after('ringover_user_id');
            $table->timestamp('ringover_linked_at')->nullable()->after('ringover_number');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['ringover_user_id']);
            $table->dropColumn(['ringover_user_id', 'ringover_number', 'ringover_linked_at']);
        });
    }
};
