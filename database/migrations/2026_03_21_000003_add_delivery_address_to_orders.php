<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_zip')->nullable()->after('channel');
            $table->string('delivery_street')->nullable()->after('delivery_zip');
            $table->string('delivery_number')->nullable()->after('delivery_street');
            $table->string('delivery_complement')->nullable()->after('delivery_number');
            $table->string('delivery_neighborhood')->nullable()->after('delivery_complement');
            $table->string('delivery_city')->nullable()->after('delivery_neighborhood');
            $table->string('delivery_state', 2)->nullable()->after('delivery_city');
            $table->decimal('delivery_latitude', 10, 7)->nullable()->after('delivery_state');
            $table->decimal('delivery_longitude', 10, 7)->nullable()->after('delivery_latitude');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_zip', 'delivery_street', 'delivery_number',
                'delivery_complement', 'delivery_neighborhood', 'delivery_city',
                'delivery_state', 'delivery_latitude', 'delivery_longitude',
            ]);
        });
    }
};
