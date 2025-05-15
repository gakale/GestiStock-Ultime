<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->string('product_name'); // Copie du nom au moment de la facturation
            $table->string('product_sku')->nullable(); // Copie du SKU
            $table->text('description')->nullable(); // Description spécifique pour cette ligne de facture
            $table->integer('quantity');
            $table->decimal('unit_price', 15, 2)->comment('Prix de vente unitaire');
            $table->decimal('discount_percentage', 5, 2)->default(0)->comment('Remise en % sur la ligne');
            $table->decimal('tax_rate', 5, 2)->default(0)->comment('Taux de taxe pour cette ligne'); // Ex: 20.00 pour 20%
            $table->decimal('line_total', 15, 2)->comment('Total de la ligne après remises et taxes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
