<?php

declare(strict_types=1);

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
        Schema::table('goods_receipt_items', function (Blueprint $table) {
            $table->foreignUuid('transaction_unit_id')->nullable()->after('purchase_order_item_id')
                  ->comment('Unité dans laquelle l\'article a été reçu');
            $table->foreign('transaction_unit_id')->references('id')->on('unit_of_measures')->nullOnDelete();

            // Ajout de transaction_quantity tout en gardant quantity_received
            // quantity_received sera maintenant interprétée comme la quantité convertie en unité de stock du produit
            $table->decimal('transaction_quantity', 15, 2)->nullable()->after('transaction_unit_id')
                  ->comment('Quantité reçue dans l\'unité de transaction');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('goods_receipt_items', function (Blueprint $table) {
            $table->dropForeign(['transaction_unit_id']);
            $table->dropColumn(['transaction_unit_id', 'transaction_quantity']);
        });
    }
};
