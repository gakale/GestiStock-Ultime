<?php

namespace App\Observers;

use App\Models\PurchaseOrderItem;
use App\Models\Product;

class PurchaseOrderItemObserver
{
    /**
     * Handle the PurchaseOrderItem "creating" event.
     * Cette méthode est appelée avant l'insertion en base de données
     */
    public function creating(PurchaseOrderItem $purchaseOrderItem): void
    {
        // Vérifier si product_name ou product_sku est null et que product_id est défini
        if ((empty($purchaseOrderItem->product_name) || empty($purchaseOrderItem->product_sku)) && !empty($purchaseOrderItem->product_id)) {
            // Récupérer le produit correspondant à cet ID
            $product = Product::find($purchaseOrderItem->product_id);
            if ($product) {
                // Remplir les champs manquants
                $purchaseOrderItem->product_name = $product->name;
                $purchaseOrderItem->product_sku = $product->sku;
            }
        }
    }
    
    /**
     * Handle the PurchaseOrderItem "created" event.
     */
    public function created(PurchaseOrderItem $purchaseOrderItem): void
    {
        //
    }

    /**
     * Handle the PurchaseOrderItem "updated" event.
     */
    public function updated(PurchaseOrderItem $purchaseOrderItem): void
    {
        //
    }

    /**
     * Handle the PurchaseOrderItem "deleted" event.
     */
    public function deleted(PurchaseOrderItem $purchaseOrderItem): void
    {
        //
    }

    /**
     * Handle the PurchaseOrderItem "restored" event.
     */
    public function restored(PurchaseOrderItem $purchaseOrderItem): void
    {
        //
    }

    /**
     * Handle the PurchaseOrderItem "force deleted" event.
     */
    public function forceDeleted(PurchaseOrderItem $purchaseOrderItem): void
    {
        //
    }
}
