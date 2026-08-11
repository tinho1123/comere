<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->string('tracking_token', 64)->nullable()->unique()->after('uuid');
            $table->decimal('current_latitude', 10, 7)->nullable()->after('notes');
            $table->decimal('current_longitude', 10, 7)->nullable()->after('current_latitude');
            $table->timestamp('location_updated_at')->nullable()->after('current_longitude');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn(['tracking_token', 'current_latitude', 'current_longitude', 'location_updated_at']);
        });
    }
};
