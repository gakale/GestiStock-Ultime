<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete(); // On ne veut pas supprimer la ligne si le produit est supprimé
            $table->foreignUuid('purchase_order_item_id')->nullable()->constrained('purchase_order_items')->nullOnDelete(); // Lien vers la ligne de la commande d'origine
            $table->integer('quantity_ordered')->nullable()->comment('Quantité commandée (copiée de la commande)');
            $table->integer('quantity_received');
            $table->decimal('unit_price', 15, 2)->comment('Prix d\'achat effectif à la réception (peut différer de la commande)');
            // On pourrait ajouter des champs pour les numéros de lot, dates de péremption si pertinent ici
            // $table->string('batch_number')->nullable();
            // $table->date('expiry_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_items');
    }
};
