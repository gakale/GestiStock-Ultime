<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Ajouter la colonne transaction_unit_id
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignUuid('transaction_unit_id')->nullable()->after('quantity_changed')
                  ->comment('Unité de mesure utilisée pour la transaction');
            $table->foreign('transaction_unit_id')
                  ->references('id')
                  ->on('unit_of_measures')
                  ->nullOnDelete();
        });

        // 2. Mettre à jour les mouvements existants
        DB::statement("
            UPDATE stock_movements sm
            SET transaction_unit_id = CASE
                WHEN sm.type LIKE 'purchase%' THEN p.purchase_unit_id
                WHEN sm.type LIKE 'sale%' THEN p.sales_unit_id
                ELSE p.stock_unit_id
            END
            FROM products p
            WHERE p.id = sm.product_id
            AND sm.transaction_unit_id IS NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['transaction_unit_id']);
            $table->dropColumn('transaction_unit_id');
        });
    }
};
