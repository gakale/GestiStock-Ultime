<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            // Utiliser une requête SQL directe pour modifier les colonnes
            DB::statement('ALTER TABLE stock_movements ALTER COLUMN quantity_changed TYPE DECIMAL(10,2)');
            DB::statement('ALTER TABLE stock_movements ALTER COLUMN new_stock_quantity_after_movement TYPE DECIMAL(10,2)');
            
            Log::info('Migration modify_stock_movements_columns exécutée avec succès');
        } catch (\Exception $e) {
            Log::error('Erreur lors de la migration modify_stock_movements_columns: ' . $e->getMessage());
            throw $e; // Relancer l'exception pour que la migration échoue
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            // Revenir au type integer
            DB::statement('ALTER TABLE stock_movements ALTER COLUMN quantity_changed TYPE INTEGER USING (quantity_changed::integer)');
            DB::statement('ALTER TABLE stock_movements ALTER COLUMN new_stock_quantity_after_movement TYPE INTEGER USING (new_stock_quantity_after_movement::integer)');
            
            Log::info('Rollback de la migration modify_stock_movements_columns exécuté avec succès');
        } catch (\Exception $e) {
            Log::error('Erreur lors du rollback de la migration modify_stock_movements_columns: ' . $e->getMessage());
            throw $e;
        }
    }
};
