<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_settings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->unique()->constrained()->onDelete('cascade');
            $table->decimal('fee_per_transaction', 8, 2)->default(0);
            $table->tinyInteger('payment_day')->default(10)->comment('Dia do mês para vencimento (1-28)');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_settings');
    }
};
