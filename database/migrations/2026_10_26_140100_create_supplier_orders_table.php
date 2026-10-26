<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('quantity_suggested')->nullable();
            $table->unsignedInteger('quantity_ordered');
            $table->date('requested_at');
            $table->date('expected_delivery_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status'], 'idx_supplier_orders_company_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_orders');
    }
};
