<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PurchaseOrderItem;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdatePurchaseOrderItemsUnits extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-purchase-order-items-units';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Met à jour les unités de transaction manquantes dans les éléments de commande fournisseur';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Mise à jour des unités de transaction pour les éléments de commande fournisseur...');

        $itemsWithoutUnit = PurchaseOrderItem::whereNull('transaction_unit_id')
            ->whereNotNull('product_id')
            ->get();

        $this->info("Nombre d'éléments à mettre à jour : " . $itemsWithoutUnit->count());

        $updatedCount = 0;
        $errorCount = 0;

        foreach ($itemsWithoutUnit as $item) {
            try {
                $product = Product::find($item->product_id);
                
                if ($product) {
                    // Utiliser l'unité d'achat par défaut, ou l'unité de stock si l'unité d'achat n'est pas définie
                    $unitId = $product->purchase_unit_id ?? $product->stock_unit_id;
                    
                    if ($unitId) {
                        $item->transaction_unit_id = $unitId;
                        $item->save();
                        $updatedCount++;
                    } else {
                        $this->warn("Le produit {$product->name} (ID: {$product->id}) n'a pas d'unité définie.");
                        $errorCount++;
                    }
                } else {
                    $this->warn("Produit non trouvé pour l'élément de commande ID: {$item->id}");
                    $errorCount++;
                }
            } catch (\Exception $e) {
                $this->error("Erreur lors de la mise à jour de l'élément ID: {$item->id} - {$e->getMessage()}");
                Log::error("Erreur lors de la mise à jour de l'unité de transaction pour l'élément de commande ID: {$item->id}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $errorCount++;
            }
        }

        $this->info("Mise à jour terminée !");
        $this->info("Éléments mis à jour avec succès : $updatedCount");
        $this->info("Éléments avec erreurs : $errorCount");

        return Command::SUCCESS;
    }
}
