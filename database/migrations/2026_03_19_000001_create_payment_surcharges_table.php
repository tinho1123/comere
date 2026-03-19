<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_surcharges', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('payment_method'); // cash, debit, credit, pix
            $table->string('type'); // fixed, percent
            $table->decimal('amount', 10, 2);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'payment_method']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_surcharges');
    }
};
