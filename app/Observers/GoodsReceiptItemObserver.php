<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\GoodsReceiptItem;
use App\Models\GoodsReceipt;
use App\Models\StockMovement;
use App\Models\Product;
use App\Models\UnitOfMeasure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class GoodsReceiptItemObserver
{
    /**
     * Handle the GoodsReceiptItem "saved" event.
     * Cette méthode est appelée après la création ou la mise à jour d'un item.
     */
    public function saved(GoodsReceiptItem $item): void
    {
        $goodsReceipt = $item->goodsReceipt;
        if (!$goodsReceipt) {
            Log::error('[GoodsReceiptItemObserver] GoodsReceipt introuvable pour l\'item ID: ' . $item->id);
            return;
        }

        // N'impacter le stock que si la réception est validée
        if (in_array($goodsReceipt->status, ['validated', 'completed'])) {
            $this->updateStockAndCreateMovement($item, $goodsReceipt);
        }
    }

    /**
     * Gère la suppression d'un item de réception.
     */
    public function deleted(GoodsReceiptItem $item): void
    {
        $goodsReceipt = $item->goodsReceipt;
        if (!$goodsReceipt) {
            Log::error('[GoodsReceiptItemObserver] GoodsReceipt introuvable pour l\'item supprimé ID: ' . $item->id);
            return;
        }

        // Si l'item est supprimé d'une réception validée, annuler l'impact sur le stock
        if (in_array($goodsReceipt->status, ['validated', 'completed'])) {
            $this->reverseStockMovement($item, $goodsReceipt);
        }
    }

    /**
     * Met à jour le stock et crée un mouvement de stock.
     */
    protected function updateStockAndCreateMovement(GoodsReceiptItem $item, GoodsReceipt $goodsReceipt): void
    {
        if (!$item->product_id || is_null($item->quantity_received) || $item->quantity_received == 0) {
            Log::info('[GoodsReceiptItemObserver] Pas de mise à jour de stock nécessaire pour l\'item ' . $item->id);
            return;
        }

        $product = Product::find($item->product_id);
        if (!$product) {
            Log::error('[GoodsReceiptItemObserver] Produit introuvable pour l\'item ' . $item->id);
            return;
        }

        DB::transaction(function () use ($product, $item, $goodsReceipt) {
            // Mise à jour du stock
            $product->increment('stock_quantity', (float)$item->quantity_received);
            $newStockQuantity = $product->fresh()->stock_quantity;

            // Création du mouvement de stock
            $notes = $this->buildMovementNotes($item, $goodsReceipt);

            StockMovement::create([
                'product_id' => $item->product_id,
                'type' => 'purchase_receipt',
                'quantity_changed' => (float)$item->quantity_received,
                'new_stock_quantity_after_movement' => $newStockQuantity,
                'related_document_type' => GoodsReceipt::class,
                'related_document_id' => $goodsReceipt->id,
                'user_id' => $goodsReceipt->received_by_user_id,
                'movement_date' => $goodsReceipt->receipt_date ?? now(),
                'transaction_unit_id' => $item->transaction_unit_id,
                'notes' => $notes,
            ]);

            Log::info('[GoodsReceiptItemObserver] Stock mis à jour avec succès', [
                'product_id' => $product->id,
                'quantity_received' => $item->quantity_received,
                'new_stock' => $newStockQuantity
            ]);
        });
    }

    /**
     * Annule l'impact sur le stock d'un item supprimé.
     */
    protected function reverseStockMovement(GoodsReceiptItem $item, GoodsReceipt $goodsReceipt): void
    {
        if (!$item->product_id || is_null($item->quantity_received) || $item->quantity_received == 0) {
            return;
        }

        $product = Product::find($item->product_id);
        if (!$product) return;

        DB::transaction(function () use ($product, $item, $goodsReceipt) {
            // Annulation de la quantité reçue
            $product->decrement('stock_quantity', (float)$item->quantity_received);
            $newStockQuantity = $product->fresh()->stock_quantity;

            // Création du mouvement d'annulation
            $notes = "Annulation réception - BR: {$goodsReceipt->receipt_number}";
            if ($item->transaction_unit_id) {
                $notes .= " - Qté: {$item->transaction_quantity} {$item->transactionUnit?->symbol}";
                if ($product->stock_unit_id != $item->transaction_unit_id) {
                    $notes .= " (converti en {$item->quantity_received} {$product->stockUnit?->symbol})";
                }
            }

            StockMovement::create([
                'product_id' => $item->product_id,
                'type' => 'purchase_receipt_cancellation',
                'quantity_changed' => -(float)$item->quantity_received,
                'new_stock_quantity_after_movement' => $newStockQuantity,
                'related_document_type' => GoodsReceipt::class,
                'related_document_id' => $goodsReceipt->id,
                'user_id' => auth()->id() ?? $goodsReceipt->received_by_user_id,
                'movement_date' => now(),
                'transaction_unit_id' => $item->transaction_unit_id,
                'notes' => $notes,
            ]);
        });
    }

    /**
     * Construit les notes pour le mouvement de stock.
     */
    protected function buildMovementNotes(GoodsReceiptItem $item, GoodsReceipt $goodsReceipt): string
    {
        $notes = "Réception BR: {$goodsReceipt->receipt_number}";
        
        if ($goodsReceipt->purchaseOrder) {
            $notes .= " - CF: {$goodsReceipt->purchaseOrder->order_number}";
        }
        
        if ($goodsReceipt->supplier_delivery_note_number) {
            $notes .= " - BL: {$goodsReceipt->supplier_delivery_note_number}";
        }

        if ($item->transaction_unit_id) {
            $notes .= "\nQté reçue: {$item->transaction_quantity} {$item->transactionUnit?->symbol}";
            if ($item->product->stock_unit_id != $item->transaction_unit_id) {
                $notes .= " (converti en {$item->quantity_received} {$item->product->stockUnit?->symbol})";
            }
        }

        return $notes;
    }
    
    /**
     * Méthode statique pour traiter un item de réception validée.
     * Cette méthode est appelée par GoodsReceiptObserver.
     */
    public static function handleValidatedReceiptItem(GoodsReceiptItem $item, GoodsReceipt $goodsReceipt): void
    {
        if (!$item->product_id || $item->quantity_received <= 0) {
            Log::info('[GoodsReceiptItemObserver] Item ignoré - données invalides', [
                'item_id' => $item->id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity_received
            ]);
            return;
        }

        $product = Product::find($item->product_id);
        if (!$product) {
            Log::error('[GoodsReceiptItemObserver] Produit introuvable pour l\'item ' . $item->id);
            return;
        }

        DB::transaction(function () use ($product, $item, $goodsReceipt) {
            // Mise à jour du stock
            $product->increment('stock_quantity', (float)$item->quantity_received);
            $newStockQuantity = $product->fresh()->stock_quantity;

            // Création du mouvement de stock
            $observer = new self();
            $notes = $observer->buildMovementNotes($item, $goodsReceipt);

            StockMovement::create([
                'product_id' => $item->product_id,
                'type' => 'purchase_receipt',
                'quantity_changed' => (float)$item->quantity_received,
                'new_stock_quantity_after_movement' => $newStockQuantity,
                'related_document_type' => GoodsReceipt::class,
                'related_document_id' => $goodsReceipt->id,
                'user_id' => $goodsReceipt->received_by_user_id ?? auth()->id(),
                'movement_date' => $goodsReceipt->receipt_date ?? now(),
                'transaction_unit_id' => $item->transaction_unit_id,
                'notes' => $notes,
            ]);

            Log::info('[GoodsReceiptItemObserver] Stock mis à jour avec succès', [
                'product_id' => $product->id,
                'quantity_received' => $item->quantity_received,
                'new_stock' => $newStockQuantity
            ]);
        });
    }
}