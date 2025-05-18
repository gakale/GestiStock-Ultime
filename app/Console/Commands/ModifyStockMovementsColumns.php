<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ModifyStockMovementsColumns extends Command
{
    protected $signature = 'app:modify-stock-movements-columns';
    protected $description = 'Modifie les colonnes quantity_changed et new_stock_quantity_after_movement de la table stock_movements en decimal';

    public function handle()
    {
        $this->info('Modification des colonnes de la table stock_movements...');
        
        try {
            // Utiliser une requête SQL directe pour modifier les colonnes
            DB::statement('ALTER TABLE stock_movements ALTER COLUMN quantity_changed TYPE DECIMAL(10,2)');
            DB::statement('ALTER TABLE stock_movements ALTER COLUMN new_stock_quantity_after_movement TYPE DECIMAL(10,2)');
            
            $this->info('Colonnes modifiées avec succès !');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Erreur lors de la modification des colonnes : ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
