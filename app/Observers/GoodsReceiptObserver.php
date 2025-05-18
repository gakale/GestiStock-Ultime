<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class GoodsReceiptObserver
{
    /**
     * Handle the GoodsReceipt "updated" event.
     */
    public function updated(GoodsReceipt $goodsReceipt): void
    {
        // Vérifier si le statut vient d'être changé à 'validated'
        if ($goodsReceipt->wasChanged('status') && 
            $goodsReceipt->status === 'validated' && 
            in_array($goodsReceipt->getOriginal('status'), ['draft', 'pending_validation'])) {
            
            $this->handleValidation($goodsReceipt);
        }
    }

    /**
     * Gère la validation d'un bon de réception.
     */
    protected function handleValidation(GoodsReceipt $goodsReceipt): void
    {
        try {
            DB::transaction(function () use ($goodsReceipt) {
                $items = $goodsReceipt->items()
                    ->with(['product', 'transactionUnit', 'product.stockUnit'])
                    ->get();

                foreach ($items as $item) {
                    if (!$item->product_id || is_null($item->quantity_received)) {
                        Log::warning('[GoodsReceiptObserver] Item ignoré - données manquantes', [
                            'item_id' => $item->id,
                            'product_id' => $item->product_id,
                            'quantity' => $item->quantity_received
                        ]);
                        continue;
                    }

                    GoodsReceiptItemObserver::handleValidatedReceiptItem($item, $goodsReceipt);
                }

                // Mettre à jour le statut de la commande fournisseur si nécessaire
                if ($goodsReceipt->purchase_order_id) {
                    $this->updatePurchaseOrderStatus($goodsReceipt);
                }

                Log::info('[GoodsReceiptObserver] BR validée avec succès', [
                    'receipt_number' => $goodsReceipt->receipt_number,
                    'items_processed' => $items->count()
                ]);
            });
        } catch (\Exception $e) {
            Log::error('[GoodsReceiptObserver] Erreur lors de la validation', [
                'receipt_number' => $goodsReceipt->receipt_number,
                'error' => $e->getMessage()
            ]);
            throw $e; // Relancer pour que Filament puisse gérer l'erreur
        }
    }

    /**
     * Met à jour le statut de la commande fournisseur.
     */
    protected function updatePurchaseOrderStatus(GoodsReceipt $goodsReceipt): void
    {
        $purchaseOrder = $goodsReceipt->purchaseOrder()->with('items')->first();
        if (!$purchaseOrder) return;

        $totalOrdered = $purchaseOrder->items->sum('quantity');
        $totalReceived = 0;

        foreach ($purchaseOrder->items as $poItem) {
            $received = GoodsReceiptItem::where('purchase_order_item_id', $poItem->id)
                ->whereHas('goodsReceipt', function ($query) {
                    $query->whereIn('status', ['validated', 'completed']);
                })
                ->sum('quantity_received');
            $totalReceived += $received;
        }

        if ($totalReceived >= $totalOrdered) {
            $purchaseOrder->status = 'fully_received';
        } elseif ($totalReceived > 0) {
            $purchaseOrder->status = 'partially_received';
        }

        $purchaseOrder->save();

        Log::info('[GoodsReceiptObserver] Statut CF mis à jour', [
            'order_number' => $purchaseOrder->order_number,
            'new_status' => $purchaseOrder->status,
            'total_ordered' => $totalOrdered,
            'total_received' => $totalReceived
        ]);
    }
}