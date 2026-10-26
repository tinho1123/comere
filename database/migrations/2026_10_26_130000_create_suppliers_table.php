<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('visit_frequency')->default('weekly');
            $table->unsignedInteger('lead_time_days')->default(0);
            $table->date('last_visit_at')->nullable();
            $table->date('next_visit_at')->nullable();
            $table->boolean('next_visit_overridden')->default(false);
            $table->timestamps();

            $table->index(['company_id'], 'idx_suppliers_company');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
