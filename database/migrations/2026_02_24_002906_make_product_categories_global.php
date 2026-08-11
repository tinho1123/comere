<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Identificar e mesclar categorias duplicadas (pelo nome)
        $duplicates = DB::table('products_categories')
            ->select('name', DB::raw('MIN(id) as canonical_id'))
            ->groupBy('name')
            ->get();

        foreach ($duplicates as $duplicate) {
            // Atualizar produtos que apontam para IDs "não canônicos"
            DB::table('products')
                ->whereIn('category_id', function ($query) use ($duplicate) {
                    $query->select('id')
                        ->from('products_categories')
                        ->where('name', $duplicate->name)
                        ->where('id', '<>', $duplicate->canonical_id);
                })
                ->update(['category_id' => $duplicate->canonical_id]);

            // Deletar as categorias duplicadas
            DB::table('products_categories')
                ->where('name', $duplicate->name)
                ->where('id', '<>', $duplicate->canonical_id)
                ->delete();
        }

        // 2. Alterar a tabela para remover company_id e adicionar unique no name
        Schema::table('products_categories', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            // Depois a coluna
            $table->dropColumn('company_id');
            // Adicionamos unique no nome para garantir que o sistema não crie duplicadas globais
            $table->unique('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products_categories', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->foreignId('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
        });
    }
};
