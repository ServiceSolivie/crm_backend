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
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('gestion_assigned_to')->nullable()->after('assigned_to')->constrained('users')->nullOnDelete();
            $table->index(['gestion_assigned_to', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['gestion_assigned_to', 'status']);
            $table->dropConstrainedForeignId('gestion_assigned_to');
        });
    }
};
