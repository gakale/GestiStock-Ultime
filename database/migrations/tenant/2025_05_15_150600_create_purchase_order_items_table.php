<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete(); // Ou restrictOnDelete si on ne veut pas supprimer la ligne si le produit est supprimé
            $table->string('product_name'); // Copie du nom au moment de la commande (peut changer plus tard)
            $table->string('product_sku')->nullable(); // Copie du SKU
            $table->integer('quantity');
            $table->decimal('unit_price', 15, 2)->comment('Prix d\'achat unitaire au moment de la commande');
            $table->decimal('discount_percentage', 5, 2)->default(0)->comment('Remise en % sur la ligne');
            $table->decimal('tax_rate', 5, 2)->default(0)->comment('Taux de taxe pour cette ligne');
            $table->decimal('line_total', 15, 2)->comment('Quantité * prix unitaire - remises + taxes de ligne');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
