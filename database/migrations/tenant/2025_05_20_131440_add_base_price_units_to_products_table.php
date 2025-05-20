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
            $table->foreignUuid('base_purchase_price_unit_id')->nullable()->after('purchase_price')
                  ->comment('Unité de référence pour le prix d\'achat');
            $table->foreign('base_purchase_price_unit_id', 'products_bppu_id_foreign') // Nom de contrainte personnalisé
                  ->references('id')->on('unit_of_measures')->nullOnDelete();

            $table->foreignUuid('base_selling_price_unit_id')->nullable()->after('selling_price')
                  ->comment('Unité de référence pour le prix de vente');
            $table->foreign('base_selling_price_unit_id', 'products_bspu_id_foreign') // Nom de contrainte personnalisé
                  ->references('id')->on('unit_of_measures')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign('products_bppu_id_foreign'); // Utiliser le nom de contrainte
            $table->dropForeign('products_bspu_id_foreign'); // Utiliser le nom de contrainte
            $table->dropColumn(['base_purchase_price_unit_id', 'base_selling_price_unit_id']);
        });
    }
};
