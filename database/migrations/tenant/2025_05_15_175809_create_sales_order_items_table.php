<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sales_order_id')->constrained('sales_orders')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->string('product_name');
            $table->string('product_sku')->nullable();
            $table->text('description')->nullable();
            $table->integer('quantity_ordered');
            $table->integer('quantity_shipped')->default(0); // Quantité déjà livrée pour cette ligne
            $table->integer('quantity_invoiced')->default(0); // Quantité déjà facturée pour cette ligne
            $table->decimal('unit_price', 15, 2);
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('line_total', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_items');
    }
};
