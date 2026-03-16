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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->uuid()->default(Str::uuid());
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->longText('description');
            $table->decimal('amount', 8, 2, true);
            $table->decimal('discounts', 8, 2, true);
            $table->boolean('active')->default(true);
            $table->decimal('total_amount', 8, 2, true);
            $table->bigInteger('quantity', false, true);
            $table->string('image')->nullable();
            $table->boolean('is_for_favored')->default(false);
            $table->decimal('favored_price', 10, 2)->nullable();
            $table->boolean('isCool')->default(false);
            $table->foreignId('category_id')->constrained('products_categories')->cascadeOnUpdate();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
