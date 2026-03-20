<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_subcategories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'active']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('subcategory_id')
                ->nullable()
                ->after('category_id')
                ->constrained('product_subcategories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['subcategory_id']);
            $table->dropColumn('subcategory_id');
        });

        Schema::dropIfExists('product_subcategories');
    }
};
