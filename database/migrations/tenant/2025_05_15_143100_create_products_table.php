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
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary(); // UUID pour l'ID du produit
            $table->string('name');
            $table->string('slug')->unique(); // Pour des URLs propres si nécessaire
            $table->text('description')->nullable();
            $table->decimal('purchase_price', 10, 2)->nullable()->comment('Prix d\'achat');
            $table->decimal('selling_price', 10, 2);      // Prix de vente
            $table->integer('stock_quantity')->default(0);
            $table->string('sku')->unique()->nullable()->comment('Stock Keeping Unit'); // Code article unique
            $table->string('barcode')->unique()->nullable()->comment('Code-barres EAN, UPC, etc.');
            // Plus tard, on ajoutera : catégorie, variantes, unités, etc.
            // $table->foreignUuid('category_id')->nullable()->constrained('product_categories')->nullOnDelete();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes(); // Si vous voulez la suppression douce
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
