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
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
        });

        \DB::statement('ALTER TABLE orders MODIFY COLUMN client_id BIGINT UNSIGNED NULL');

        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
        });

        \DB::statement('ALTER TABLE orders MODIFY COLUMN client_id BIGINT UNSIGNED NOT NULL');

        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
        });
    }
};
