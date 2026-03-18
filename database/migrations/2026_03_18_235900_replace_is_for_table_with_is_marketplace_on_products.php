<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'is_for_table')) {
                $table->dropColumn('is_for_table');
            }

            $table->boolean('is_marketplace')->default(false)->after('is_for_favored');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_marketplace');
            $table->boolean('is_for_table')->default(false)->after('is_for_favored');
        });
    }
};
