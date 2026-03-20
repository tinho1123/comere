<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_id')->unique()->constrained()->onDelete('cascade');
            $table->foreignId('driver_id')->constrained()->onDelete('restrict');
            $table->enum('status', ['dispatched', 'delivered', 'failed'])->default('dispatched');
            $table->decimal('driver_fee', 8, 2);
            $table->boolean('is_paid')->default(false);
            $table->timestamp('dispatched_at');
            $table->timestamp('delivered_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status'], 'idx_deliveries_company_status');
            $table->index(['driver_id', 'is_paid', 'status'], 'idx_deliveries_driver_paid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
