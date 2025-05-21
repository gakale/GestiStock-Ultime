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
        Schema::table('products', function (Blueprint $table) {
            // Option A: Supprimer la colonne stock_quantity car le stock réel sera désormais dans product_location_stocks
            $table->dropColumn('stock_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Rétablir la colonne stock_quantity si on revient en arrière
            $table->decimal('stock_quantity', 15, 2)->default(0);
        });
    }
};
