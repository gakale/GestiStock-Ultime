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
        Schema::table('stock_movements', function (Blueprint $table) {
            // Modifier la colonne quantity_changed de integer à decimal(10,2)
            $table->decimal('quantity_changed', 10, 2)->change();
            
            // Modifier également la colonne new_stock_quantity_after_movement pour cohérence
            $table->decimal('new_stock_quantity_after_movement', 10, 2)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            // Remettre la colonne quantity_changed en integer
            $table->integer('quantity_changed')->change();
            
            // Remettre également la colonne new_stock_quantity_after_movement en integer
            $table->integer('new_stock_quantity_after_movement')->change();
        });
    }
};
