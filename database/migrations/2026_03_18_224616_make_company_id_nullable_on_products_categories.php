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

        $this->dropForeignIfExists('products_categories', 'products_categories_company_id_foreign');

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

        $this->dropForeignIfExists('products_categories', 'products_categories_company_id_foreign');

        DB::statement('ALTER TABLE products_categories MODIFY COLUMN company_id BIGINT UNSIGNED NOT NULL');

        Schema::table('products_categories', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });
    }

    private function dropForeignIfExists(string $table, string $foreignKey): void
    {
        $exists = DB::select("
            SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND CONSTRAINT_NAME = ?
              AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ", [$table, $foreignKey]);

        if ($exists) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$foreignKey}`");
        }
    }
};
