<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_assignment_histories', function (Blueprint $table) {
            $table->foreignId('assigned_by')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('lead_assignment_histories', function (Blueprint $table) {
            $table->foreignId('assigned_by')->nullable(false)->change();
        });
    }
};
