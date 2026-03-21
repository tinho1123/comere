<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_favorite_companies', function (Blueprint $table) {
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['client_id', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_favorite_companies');
    }
};
