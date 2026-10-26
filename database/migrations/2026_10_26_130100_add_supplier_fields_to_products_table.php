<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('category_id')->constrained()->onDelete('set null');
            $table->unsignedInteger('purchase_unit_size')->default(1)->after('supplier_id');
            $table->unsignedInteger('min_stock')->nullable()->after('purchase_unit_size');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropColumn(['purchase_unit_size', 'min_stock']);
        });
    }
};
