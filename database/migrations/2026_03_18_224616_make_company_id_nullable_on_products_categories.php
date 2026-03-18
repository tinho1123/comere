<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('products_categories', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
        });

        DB::statement('ALTER TABLE products_categories MODIFY COLUMN company_id BIGINT UNSIGNED NULL');

        Schema::table('products_categories', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('products_categories', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
        });

        DB::statement('ALTER TABLE products_categories MODIFY COLUMN company_id BIGINT UNSIGNED NOT NULL');

        Schema::table('products_categories', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });
    }
};
