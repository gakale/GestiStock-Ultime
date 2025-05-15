<?php

namespace App\Observers;

use App\Models\GoodsReceiptItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockMovement; // Importer le modèle StockMovement
use App\Models\GoodsReceipt;  // Importer GoodsReceipt pour related_document_type
use App\Models\TenantUser; // Importer TenantUser pour la vérification de type

class GoodsReceiptItemObserver
{
    /**
     * Handle the GoodsReceiptItem "created" event.
     */
    public function created(GoodsReceiptItem $goodsReceiptItem): void
    {
        $product = $goodsReceiptItem->product;
        $newStockQuantity = $product->stock_quantity; // Stock actuel avant incrémentation

        if ($product) {
            // 1. Mettre à jour le stock du produit
            $product->increment('stock_quantity', $goodsReceiptItem->quantity_received);
            $newStockQuantity = $product->fresh()->stock_quantity; // Récupérer la nouvelle quantité après incrémentation
        }

        // 2. Créer un mouvement de stock
        StockMovement::create([
            'product_id' => $goodsReceiptItem->product_id,
            'type' => 'purchase_receipt', // Type de mouvement clair
            'quantity_changed' => $goodsReceiptItem->quantity_received, // Positive
            'new_stock_quantity_after_movement' => $newStockQuantity,
            'related_document_type' => GoodsReceipt::class, // Nom de la classe du modèle lié
            'related_document_id' => $goodsReceiptItem->goods_receipt_id,
            'user_id' => $goodsReceiptItem->goodsReceipt?->received_by_user_id, // Opérateur
            'movement_date' => $goodsReceiptItem->goodsReceipt?->receipt_date ?? now(),
            'notes' => 'Réception depuis commande fournisseur: ' . $goodsReceiptItem->goodsReceipt?->purchaseOrder?->order_number . ' / BL: ' . $goodsReceiptItem->goodsReceipt?->supplier_delivery_note_number,
        ]);


        // 3. Mettre à jour le statut de la commande fournisseur (si liée)
        if ($goodsReceiptItem->purchase_order_item_id && $goodsReceiptItem->goodsReceipt->purchase_order_id) {
            $purchaseOrder = $goodsReceiptItem->goodsReceipt->purchaseOrder()->with('items')->first();
            if ($purchaseOrder) {
                $totalOrdered = 0;
                $totalReceivedForOrder = 0;

                foreach ($purchaseOrder->items as $poItem) {
                    $totalOrdered += $poItem->quantity;
                    // Somme de toutes les quantités reçues pour cette ligne de commande fournisseur spécifique
                    $totalReceivedForPoItem = GoodsReceiptItem::where('purchase_order_item_id', $poItem->id)
                                                                ->sum('quantity_received');
                    $totalReceivedForOrder += $totalReceivedForPoItem;
                }

                if ($totalReceivedForOrder >= $totalOrdered) {
                    $purchaseOrder->status = 'fully_received';
                } elseif ($totalReceivedForOrder > 0) {
                    $purchaseOrder->status = 'partially_received';
                }
                // Si $totalReceivedForOrder est 0 mais la commande était 'ordered', elle le reste.
                // On ne gère pas le retour à 'ordered' si on annule une réception ici,
                // cela nécessiterait une logique dans 'deleted' de GoodsReceiptItem.
                $purchaseOrder->save();
            }
        }
    }

    /**
     * Handle the GoodsReceiptItem "deleted" event.
     */
    public function deleted(GoodsReceiptItem $goodsReceiptItem): void
    {
        $product = $goodsReceiptItem->product;
        $newStockQuantity = $product->stock_quantity; // Stock actuel avant décrémentation

        if ($product) {
            // 1. Diminuer le stock du produit
            $product->decrement('stock_quantity', $goodsReceiptItem->quantity_received);
            $newStockQuantity = $product->fresh()->stock_quantity; // Récupérer la nouvelle quantité après décrémentation
        }

        // 2. Créer un mouvement de stock d'annulation/correction
        StockMovement::create([
            'product_id' => $goodsReceiptItem->product_id,
            'type' => 'purchase_receipt_cancellation', // Type de mouvement clair
            'quantity_changed' => -$goodsReceiptItem->quantity_received, // Négative
            'new_stock_quantity_after_movement' => $newStockQuantity,
            'related_document_type' => GoodsReceipt::class,
            'related_document_id' => $goodsReceiptItem->goods_receipt_id,
            'user_id' => auth()->check() && auth()->user() instanceof TenantUser ? auth()->user()->getKey() : null, // L'utilisateur qui annule
            'movement_date' => now(), // La date de l'annulation
            'reason' => 'Annulation item de réception GRN: ' . $goodsReceiptItem->goodsReceipt->receipt_number,
        ]);


        // 3. Mettre à jour le statut de la commande fournisseur (logique existante)
        if ($goodsReceiptItem->purchase_order_item_id && $goodsReceiptItem->goodsReceipt->purchase_order_id) {
            // ... (votre logique de mise à jour du statut de PurchaseOrder reste ici) ...
            $purchaseOrder = $goodsReceiptItem->goodsReceipt->purchaseOrder()->with('items')->first();
            if ($purchaseOrder) {
                $allLinesOrdered = $purchaseOrder->items->sum('quantity');
                $totalCurrentlyReceivedForOrder = 0;
                foreach ($purchaseOrder->items as $poItem) {
                    $totalCurrentlyReceivedForOrder += GoodsReceiptItem::where('purchase_order_item_id', $poItem->id)->sum('quantity_received');
                }

                if ($totalCurrentlyReceivedForOrder <= 0) {
                    if(in_array($purchaseOrder->status, ['partially_received', 'fully_received'])){
                        $purchaseOrder->status = 'ordered';
                    }
                } elseif ($totalCurrentlyReceivedForOrder < $allLinesOrdered) {
                    $purchaseOrder->status = 'partially_received';
                } else {
                    $purchaseOrder->status = 'fully_received';
                }
                $purchaseOrder->save();
            }
        }
    }
}