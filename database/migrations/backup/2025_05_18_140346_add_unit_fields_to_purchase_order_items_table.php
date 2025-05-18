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
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->foreignUuid('transaction_unit_id')->nullable()->after('product_sku')
                  ->comment('Unité de commande');
            $table->foreign('transaction_unit_id')->references('id')->on('unit_of_measures')->nullOnDelete();
            // 'quantity' existante devient la quantité dans l'unité de transaction
            // 'unit_price' existant est le prix dans l'unité de transaction
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropForeign(['transaction_unit_id']);
            $table->dropColumn('transaction_unit_id');
        });
    }
};
