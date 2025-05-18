<?php

declare(strict_types=1);

use App\Models\PurchaseOrderItem;
use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->updatePurchaseOrderItemsUnits();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cette migration ne peut pas être annulée de manière sécurisée
    }

    /**
     * Met à jour les unités de transaction manquantes dans les éléments de commande fournisseur
     */
    private function updatePurchaseOrderItemsUnits(): void
    {
        $itemsWithoutUnit = PurchaseOrderItem::whereNull('transaction_unit_id')
            ->whereNotNull('product_id')
            ->get();

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
                        Log::warning("Le produit {$product->name} (ID: {$product->id}) n'a pas d'unité définie.");
                        $errorCount++;
                    }
                } else {
                    Log::warning("Produit non trouvé pour l'élément de commande ID: {$item->id}");
                    $errorCount++;
                }
            } catch (\Exception $e) {
                Log::error("Erreur lors de la mise à jour de l'unité de transaction pour l'élément de commande ID: {$item->id}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $errorCount++;
            }
        }

        Log::info("Migration updatePurchaseOrderItemsUnits terminée: $updatedCount éléments mis à jour, $errorCount erreurs");
    }
};
