<?php

use App\Enums\PaymentStatusEnum;
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
            $table->decimal('expected_revenue', 10, 2)->nullable()->after('comment');
            $table->enum('payment_status', PaymentStatusEnum::values())->nullable()->after('expected_revenue');
            $table->timestamp('validated_at')->nullable()->after('payment_status');

            $table->index('payment_status');
            $table->index('validated_at');
            $table->index(['team_id', 'payment_status']);
            $table->index(['assigned_to', 'payment_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['team_id', 'payment_status']);
            $table->dropIndex(['assigned_to', 'payment_status']);
            $table->dropIndex(['payment_status']);
            $table->dropIndex(['validated_at']);

            $table->dropColumn(['expected_revenue', 'payment_status', 'validated_at']);
        });
    }
};
