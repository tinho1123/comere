<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_company', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained()->onDelete('cascade');
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->decimal('delivery_fee', 8, 2)->default(0);
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['driver_id', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_company');
    }
};
