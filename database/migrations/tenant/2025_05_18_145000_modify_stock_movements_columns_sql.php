<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Utiliser une requête SQL directe pour modifier les colonnes
        DB::statement('ALTER TABLE stock_movements ALTER COLUMN quantity_changed TYPE DECIMAL(10,2)');
        DB::statement('ALTER TABLE stock_movements ALTER COLUMN new_stock_quantity_after_movement TYPE DECIMAL(10,2)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remettre les colonnes en integer
        DB::statement('ALTER TABLE stock_movements ALTER COLUMN quantity_changed TYPE INTEGER USING (quantity_changed::integer)');
        DB::statement('ALTER TABLE stock_movements ALTER COLUMN new_stock_quantity_after_movement TYPE INTEGER USING (new_stock_quantity_after_movement::integer)');
    }
};
