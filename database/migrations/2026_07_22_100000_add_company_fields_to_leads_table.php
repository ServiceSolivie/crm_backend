<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('address')->nullable()->after('city');
            $table->string('company_status')->nullable()->after('client_type');
            $table->string('company_legal_form')->nullable()->after('company_status');
            $table->string('company_sector')->nullable()->after('company_legal_form');
            $table->string('company_employee_count')->nullable()->after('company_sector');
            $table->string('company_name')->nullable()->after('company_employee_count');
            $table->string('company_annual_revenue')->nullable()->after('company_name');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'address',
                'company_status',
                'company_legal_form',
                'company_sector',
                'company_employee_count',
                'company_name',
                'company_annual_revenue',
            ]);
        });
    }
};
