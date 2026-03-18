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

        // Se a coluna não existe, não há nada a fazer
        if (! Schema::hasColumn('products_categories', 'company_id')) {
            return;
        }

        // Remove FK se existir, ignora se não existir
        try {
            Schema::table('products_categories', function (Blueprint $table) {
                $table->dropForeign(['company_id']);
            });
        } catch (Throwable) {
        }

        DB::statement('ALTER TABLE products_categories MODIFY COLUMN company_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE products_categories MODIFY COLUMN company_id BIGINT UNSIGNED NOT NULL');
    }
};
