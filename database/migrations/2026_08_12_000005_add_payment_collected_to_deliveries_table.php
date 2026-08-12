<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->boolean('payment_collected')->default(false)->after('notes');
            $table->timestamp('payment_collected_at')->nullable()->after('payment_collected');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn(['payment_collected', 'payment_collected_at']);
        });
    }
};
