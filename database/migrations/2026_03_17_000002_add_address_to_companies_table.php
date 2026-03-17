<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('address_zip', 9)->nullable()->after('name');
            $table->string('address_street')->nullable()->after('address_zip');
            $table->string('address_number', 20)->nullable()->after('address_street');
            $table->string('address_complement')->nullable()->after('address_number');
            $table->string('address_neighborhood')->nullable()->after('address_complement');
            $table->string('address_city')->nullable()->after('address_neighborhood');
            $table->string('address_state', 2)->nullable()->after('address_city');
            $table->decimal('latitude', 10, 7)->nullable()->after('address_state');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'address_zip', 'address_street', 'address_number',
                'address_complement', 'address_neighborhood', 'address_city',
                'address_state', 'latitude', 'longitude',
            ]);
        });
    }
};
