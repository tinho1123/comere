<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_losses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('quantity');
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id'], 'idx_stock_losses_company');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_losses');
    }
};
