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
        Schema::table('table_session_items', function (Blueprint $table) {
            $table->boolean('is_delivered')->default(false)->after('total_amount');
            $table->timestamp('delivered_at')->nullable()->after('is_delivered');
        });
    }

    public function down(): void
    {
        Schema::table('table_session_items', function (Blueprint $table) {
            $table->dropColumn(['is_delivered', 'delivered_at']);
        });
    }
};
