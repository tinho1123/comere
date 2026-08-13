<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('delivery_id')->unique()->constrained()->onDelete('cascade');
            $table->foreignId('driver_id')->constrained()->onDelete('cascade');
            $table->enum('rating', ['good', 'bad']);
            $table->timestamps();

            $table->index(['company_id', 'rating'], 'idx_delivery_feedbacks_company_rating');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_feedbacks');
    }
};
