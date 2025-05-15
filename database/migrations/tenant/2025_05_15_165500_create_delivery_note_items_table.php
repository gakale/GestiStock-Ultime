<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_note_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('delivery_note_id')->constrained('delivery_notes')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignUuid('invoice_item_id')->nullable()->constrained('invoice_items')->nullOnDelete(); // Lien vers la ligne de facture d'origine
            // $table->foreignUuid('sales_order_item_id')->nullable()->constrained('sales_order_items')->nullOnDelete(); // Lien vers la ligne de commande client

            $table->string('product_name'); // Copie
            $table->string('product_sku')->nullable(); // Copie
            $table->integer('quantity_ordered')->nullable()->comment('Quantité sur la facture/commande source');
            $table->integer('quantity_shipped');
            // Pas de prix ici, le prix est sur la facture/commande
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_note_items');
    }
};
