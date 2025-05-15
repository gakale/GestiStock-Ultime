<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignUuid('product_category_id')
                  ->nullable()
                  ->after('barcode') // Ou l'endroit que vous préférez
                  ->constrained('product_categories')
                  ->nullOnDelete(); // Si une catégorie est supprimée, met la catégorie du produit à null
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['product_category_id']);
            $table->dropColumn('product_category_id');
        });
    }
};
