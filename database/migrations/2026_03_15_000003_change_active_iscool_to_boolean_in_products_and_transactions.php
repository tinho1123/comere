<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE products MODIFY active TINYINT(1) NOT NULL DEFAULT 1');
        DB::statement('ALTER TABLE products MODIFY isCool TINYINT(1) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE products_categories MODIFY active TINYINT(1) NOT NULL DEFAULT 1');
        DB::statement('ALTER TABLE transactions MODIFY active TINYINT(1) NOT NULL DEFAULT 1');
        DB::statement('ALTER TABLE transactions MODIFY isCool TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE products MODIFY active ENUM('Y','N') NOT NULL DEFAULT 'Y'");
        DB::statement("ALTER TABLE products MODIFY isCool ENUM('Y','N') NOT NULL");
        DB::statement("ALTER TABLE products_categories MODIFY active ENUM('Y','N') NOT NULL DEFAULT 'Y'");
        DB::statement("ALTER TABLE transactions MODIFY active ENUM('Y','N') NOT NULL DEFAULT 'Y'");
        DB::statement("ALTER TABLE transactions MODIFY isCool ENUM('Y','N') NOT NULL");
    }
};
