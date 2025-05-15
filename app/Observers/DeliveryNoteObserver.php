<?php

namespace App\Observers;

use App\Models\DeliveryNote;
use App\Models\DeliveryNoteItem; // Importer
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\TenantUser;
use Illuminate\Support\Facades\Log;

class DeliveryNoteObserver
{
    /**
     * Handle the DeliveryNote "updated" event.
     */
    public function updated(DeliveryNote $deliveryNote): void
    {
        Log::info("[DeliveryNoteObserver] UPDATED event for DeliveryNote ID: {$deliveryNote->id}, New Status: {$deliveryNote->status}");

        // Vérifier si le statut a changé et est maintenant un statut de déduction de stock
        if ($deliveryNote->wasChanged('status')) {
            $newStatus = $deliveryNote->status;
            $previousStatus = $deliveryNote->getOriginal('status');
            Log::info("[DeliveryNoteObserver] Status changed from '{$previousStatus}' to '{$newStatus}'.");

            $stockDeductingStatuses = ['shipped', 'delivered'];
            $stockReIncrementStatuses = ['draft', 'cancelled']; // Statuts qui pourraient annuler une sortie de stock

            // Cas 1: Le statut passe à un statut de déduction de stock
            // ET il n'était PAS auparavant dans un statut où le stock était déjà déduit.
            if (in_array($newStatus, $stockDeductingStatuses) && !in_array($previousStatus, $stockDeductingStatuses)) {
                Log::info("[DeliveryNoteObserver] Status changed to stock-deducting. Processing items for DeliveryNote ID: {$deliveryNote->id}");
                foreach ($deliveryNote->items()->with('product')->get() as $item) {
                    $this->deductStockAndLogMovement($item, $deliveryNote);
                }
            }
            // Cas 2: Le statut quitte un état de déduction de stock pour un état d'annulation/brouillon
            // ET il était AUPARAVANT dans un statut où le stock avait été déduit.
            elseif (in_array($newStatus, $stockReIncrementStatuses) && in_array($previousStatus, $stockDeductingStatuses)) {
                 Log::info("[DeliveryNoteObserver] Status changed to stock-reincrementing. Processing items for DeliveryNote ID: {$deliveryNote->id}");
                foreach ($deliveryNote->items()->with('product')->get() as $item) {
                    $this->reIncrementStockAndLogMovement($item, $deliveryNote);
                }
            }
        }
    }

    /**
     * Handle the DeliveryNote "deleting" event.
     * Si on supprime un BL qui avait déjà sorti du stock.
     */
    public function deleting(DeliveryNote $deliveryNote): void
    {
        Log::info("[DeliveryNoteObserver] DELETING event for DeliveryNote ID: {$deliveryNote->id}, Status: {$deliveryNote->status}");
        $stockDeductingStatuses = ['shipped', 'delivered'];
        if (in_array($deliveryNote->status, $stockDeductingStatuses)) {
            Log::info("[DeliveryNoteObserver] Re-incrementing stock due to DeliveryNote deletion.");
            foreach ($deliveryNote->items()->with('product')->get() as $item) {
                $this->reIncrementStockAndLogMovement($item, $deliveryNote);
            }
        }
    }


    // Les méthodes suivantes sont des helpers, identiques à celles de DeliveryNoteItemObserver
    // Vous pourriez les mettre dans un Trait si vous voulez les réutiliser proprement.
    protected function deductStockAndLogMovement(DeliveryNoteItem $item, DeliveryNote $deliveryNote): void
    {
        $product = $item->product;
        if (!$product) {
            Log::error("[DeliveryNoteObserver] Product not found for DeliveryNoteItem ID: {$item->id}");
            return;
        }

        // Vérifier si un mouvement de déduction a déjà été fait pour cet item et ce BL
        // pour éviter les doubles déductions si l'observer est appelé plusieurs fois
        // (ceci est une sécurité, la logique de transition de statut devrait déjà gérer cela)
        $existingMovement = StockMovement::where('related_document_type', DeliveryNote::class)
                                        ->where('related_document_id', $deliveryNote->id)
                                        ->where('product_id', $item->product_id)
                                        // ->where('original_item_id', $item->id) // Si vous ajoutez une colonne pour l'ID de l'item original
                                        ->where('quantity_changed', -$item->quantity_shipped) // Recherche un mouvement de déduction EXACT
                                        ->first();
        if ($existingMovement) {
            Log::info("[DeliveryNoteObserver] Stock deduction movement already exists for Item ID {$item->id} and DeliveryNote ID {$deliveryNote->id}. Skipping.");
            return;
        }


        Log::info("[DeliveryNoteObserver] Deducting stock: Product '{$product->name}', Qty: {$item->quantity_shipped}");
        $product->decrement('stock_quantity', $item->quantity_shipped);
        $newStockQuantity = $product->fresh()->stock_quantity;

        StockMovement::create([
            'product_id' => $item->product_id,
            'type' => 'sale_delivery',
            'quantity_changed' => -$item->quantity_shipped,
            'new_stock_quantity_after_movement' => $newStockQuantity,
            'related_document_type' => DeliveryNote::class,
            'related_document_id' => $deliveryNote->id,
            'user_id' => $deliveryNote->user_id,
            'movement_date' => $deliveryNote->delivery_date,
            'notes' => "Livraison BL: {$deliveryNote->delivery_note_number} (Item ID: {$item->id})", // Ajouter l'ID de l'item pour plus de clarté
        ]);
        Log::info("[DeliveryNoteObserver] StockMovement 'sale_delivery' created for Product ID: {$product->id}, Item ID: {$item->id}");
    }

    protected function reIncrementStockAndLogMovement(DeliveryNoteItem $item, DeliveryNote $deliveryNote): void
    {
        $product = $item->product;
        if (!$product) {
            Log::error("[DeliveryNoteObserver] Product not found for reIncrement on DeliveryNoteItem ID: {$item->id}");
            return;
        }

        // Sécurité pour éviter double réintégration
        $existingMovement = StockMovement::where('related_document_type', DeliveryNote::class)
                                        ->where('related_document_id', $deliveryNote->id)
                                        ->where('product_id', $item->product_id)
                                        ->where('quantity_changed', $item->quantity_shipped) // Recherche une réintégration EXACTE
                                        ->where('type', 'delivery_cancellation')
                                        ->first();
        if ($existingMovement) {
            Log::info("[DeliveryNoteObserver] Stock re-increment movement already exists for Item ID {$item->id} and DeliveryNote ID {$deliveryNote->id}. Skipping.");
            return;
        }

        Log::info("[DeliveryNoteObserver] Re-incrementing stock: Product '{$product->name}', Qty: {$item->quantity_shipped}");
        $product->increment('stock_quantity', $item->quantity_shipped);
        $newStockQuantity = $product->fresh()->stock_quantity;

        StockMovement::create([
            'product_id' => $item->product_id,
            'type' => 'delivery_cancellation',
            'quantity_changed' => $item->quantity_shipped,
            'new_stock_quantity_after_movement' => $newStockQuantity,
            'related_document_type' => DeliveryNote::class,
            'related_document_id' => $deliveryNote->id,
            'user_id' => auth()->check() && auth()->user() instanceof TenantUser ? auth()->user()->getKey() : $deliveryNote->user_id,
            'movement_date' => now(),
            'reason' => "Annulation/Modif BL: {$deliveryNote->delivery_note_number} (Item ID: {$item->id})",
        ]);
        Log::info("[DeliveryNoteObserver] StockMovement 'delivery_cancellation' created for Product ID: {$product->id}, Item ID: {$item->id}");
    }
}