<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---------------------------------------------------------------
        // products
        // Queries: active products per company, by category, search by name
        // ---------------------------------------------------------------
        Schema::table('products', function (Blueprint $table) {
            $table->index(['company_id', 'active'], 'idx_products_company_active');
            $table->index(['company_id', 'category_id'], 'idx_products_company_category');
            $table->index(['company_id', 'is_for_favored'], 'idx_products_company_favored');
            $table->index(['name'], 'idx_products_name');
        });

        // ---------------------------------------------------------------
        // orders
        // Queries: orders per company by status, by client, by date range
        // (billing service uses company_id + created_at range heavily)
        // ---------------------------------------------------------------
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['company_id', 'status'], 'idx_orders_company_status');
            $table->index(['company_id', 'client_id'], 'idx_orders_company_client');
            $table->index(['company_id', 'created_at'], 'idx_orders_company_created');
        });

        // ---------------------------------------------------------------
        // order_items
        // Queries: items per order + product (ship() decrement uses this)
        // ---------------------------------------------------------------
        Schema::table('order_items', function (Blueprint $table) {
            $table->index(['order_id', 'product_id'], 'idx_order_items_order_product');
        });

        // ---------------------------------------------------------------
        // transactions
        // No indexes beyond FK — very common query target
        // ---------------------------------------------------------------
        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['company_id', 'type'], 'idx_transactions_company_type');
            $table->index(['company_id', 'created_at'], 'idx_transactions_company_created');
            $table->index(['client_id'], 'idx_transactions_client');
        });

        // ---------------------------------------------------------------
        // favored_transactions
        // Queries: client transactions per company, upcoming due dates
        // ---------------------------------------------------------------
        Schema::table('favored_transactions', function (Blueprint $table) {
            $table->index(['company_id', 'client_id'], 'idx_favored_transactions_company_client');
            $table->index(['company_id', 'due_date'], 'idx_favored_transactions_company_due');
            $table->index(['due_date'], 'idx_favored_transactions_due_date');
        });

        // ---------------------------------------------------------------
        // favored_debts
        // Queries: debts per company+client, by status, by due date
        // ---------------------------------------------------------------
        Schema::table('favored_debts', function (Blueprint $table) {
            $table->index(['company_id', 'status'], 'idx_favored_debts_company_status');
            $table->index(['company_id', 'due_date'], 'idx_favored_debts_company_due');
            $table->index(['due_date'], 'idx_favored_debts_due_date');
        });

        // ---------------------------------------------------------------
        // billing_cycles
        // Queries: overdue check (status + due_date), per company by status
        // ---------------------------------------------------------------
        Schema::table('billing_cycles', function (Blueprint $table) {
            $table->index(['status', 'due_date'], 'idx_billing_cycles_status_due');
            $table->index(['company_id', 'status'], 'idx_billing_cycles_company_status');
        });

        // ---------------------------------------------------------------
        // companies
        // Queries: active companies in marketplace listing
        // ---------------------------------------------------------------
        Schema::table('companies', function (Blueprint $table) {
            $table->index(['active'], 'idx_companies_active');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('idx_products_company_active');
            $table->dropIndex('idx_products_company_category');
            $table->dropIndex('idx_products_company_favored');
            $table->dropIndex('idx_products_name');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_company_status');
            $table->dropIndex('idx_orders_company_client');
            $table->dropIndex('idx_orders_company_created');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('idx_order_items_order_product');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('idx_transactions_company_type');
            $table->dropIndex('idx_transactions_company_created');
            $table->dropIndex('idx_transactions_client');
        });

        Schema::table('favored_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_favored_transactions_company_client');
            $table->dropIndex('idx_favored_transactions_company_due');
            $table->dropIndex('idx_favored_transactions_due_date');
        });

        Schema::table('favored_debts', function (Blueprint $table) {
            $table->dropIndex('idx_favored_debts_company_status');
            $table->dropIndex('idx_favored_debts_company_due');
            $table->dropIndex('idx_favored_debts_due_date');
        });

        Schema::table('billing_cycles', function (Blueprint $table) {
            $table->dropIndex('idx_billing_cycles_status_due');
            $table->dropIndex('idx_billing_cycles_company_status');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex('idx_companies_active');
        });
    }
};
