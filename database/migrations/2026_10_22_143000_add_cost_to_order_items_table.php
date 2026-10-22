<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Custo do produto no momento da venda. Guardado aqui (em vez de
            // sempre consultar products.cost) pra o lucro de vendas antigas não
            // mudar quando o custo cadastrado do produto for reajustado depois.
            $table->decimal('cost', 10, 2)->nullable()->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('cost');
        });
    }
};
