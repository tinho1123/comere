<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained()->onDelete('cascade');
            $table->string('endpoint', 500);
            $table->string('public_key', 512)->nullable();
            $table->string('auth_token', 512)->nullable();
            $table->timestamps();

            $table->unique(['driver_id', 'endpoint'], 'unique_driver_endpoint');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_push_subscriptions');
    }
};
