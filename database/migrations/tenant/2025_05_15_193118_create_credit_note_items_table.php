<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_note_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('credit_note_id')->constrained('credit_notes')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete(); // Ou nullable si l'avoir ne concerne pas un produit
            $table->foreignUuid('invoice_item_id')->nullable()->constrained('invoice_items')->nullOnDelete(); // Ligne de la facture d'origine

            $table->string('product_name');
            $table->string('product_sku')->nullable();
            $table->text('description')->nullable();
            $table->integer('quantity'); // Quantité créditée (toujours positive ici)
            $table->decimal('unit_price', 15, 2); // Prix auquel le produit a été vendu/crédité
            $table->decimal('discount_percentage', 5, 2)->default(0); // Si une remise spécifique est appliquée sur la ligne d'avoir
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('line_total', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_note_items');
    }
};
