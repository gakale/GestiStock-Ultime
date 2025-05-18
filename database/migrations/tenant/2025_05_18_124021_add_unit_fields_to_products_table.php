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
        Schema::table('products', function (Blueprint $table) {
            // L'unité dans laquelle le stock principal est géré et affiché
            $table->foreignUuid('stock_unit_id')->nullable()->after('tax_rate_id') // Ou après un autre champ pertinent
                  ->comment('Unité de gestion du stock principal');
            $table->foreign('stock_unit_id')->references('id')->on('unit_of_measures')->nullOnDelete();

            // Unité d'achat par défaut (peut être différente de l'unité de stock)
            $table->foreignUuid('purchase_unit_id')->nullable()->after('stock_unit_id')
                  ->comment('Unité d\'achat par défaut');
            $table->foreign('purchase_unit_id')->references('id')->on('unit_of_measures')->nullOnDelete();

            // Unité de vente par défaut (peut être différente de l'unité de stock)
            $table->foreignUuid('sales_unit_id')->nullable()->after('purchase_unit_id')
                  ->comment('Unité de vente par défaut');
            $table->foreign('sales_unit_id')->references('id')->on('unit_of_measures')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Important de supprimer les clés étrangères avant les colonnes
            $table->dropForeign(['stock_unit_id']);
            $table->dropForeign(['purchase_unit_id']);
            $table->dropForeign(['sales_unit_id']);

            $table->dropColumn(['stock_unit_id', 'purchase_unit_id', 'sales_unit_id']);
        });
    }
};