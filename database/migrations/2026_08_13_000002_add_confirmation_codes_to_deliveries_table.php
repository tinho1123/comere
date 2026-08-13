<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->string('pickup_code', 4)->nullable()->after('driver_fee');
            $table->string('payment_confirmation_code', 4)->nullable()->after('pickup_code');
            $table->timestamp('picked_up_at')->nullable()->after('dispatched_at');
            $table->string('failure_reason')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn(['pickup_code', 'payment_confirmation_code', 'picked_up_at', 'failure_reason']);
        });
    }
};
