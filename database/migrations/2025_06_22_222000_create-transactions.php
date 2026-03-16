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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid()->default(Str::uuid());
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('fees_id')->constrained('fees')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('name');
            $table->longText('description');
            $table->decimal('amount', 8, 2, true);
            $table->decimal('discounts', 8, 2, true);
            $table->decimal('fees', 8, 2, true);
            $table->boolean('active')->default(true);
            $table->decimal('total_amount', 8, 2, true);
            $table->bigInteger('quantity', false, true);
            $table->string('image')->nullable();
            $table->boolean('isCool')->default(false);
            $table->string('category_name');
            $table->foreignId('category_id')->constrained('products_categories')->cascadeOnUpdate();
            $table->string('client_name');
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('type')->default('sale'); // sale, payment, credit, etc.
            $table->string('payment_method')->nullable(); // pix, cash, debit_card, credit_card, etc.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
