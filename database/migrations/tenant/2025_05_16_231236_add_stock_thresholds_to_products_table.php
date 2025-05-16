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
        Schema::table('products', function (Blueprint $table) {
            // Seuil minimum avant alerte de rupture potentielle
            $table->decimal('stock_min_threshold', 15, 2)->nullable()->after('stock_quantity')->comment('Seuil de stock minimum');
            // Seuil auquel on devrait idéalement commander pour ne pas tomber sous le min
            $table->decimal('stock_reorder_point', 15, 2)->nullable()->after('stock_min_threshold')->comment('Point de réapprovisionnement');
            // Seuil maximum pour éviter le surstockage (optionnel)
            $table->decimal('stock_max_threshold', 15, 2)->nullable()->after('stock_reorder_point')->comment('Seuil de stock maximum');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['stock_min_threshold', 'stock_reorder_point', 'stock_max_threshold']);
        });
    }
};
