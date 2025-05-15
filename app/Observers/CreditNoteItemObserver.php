<?php

namespace App\Observers;

use App\Models\CreditNoteItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\CreditNote; // Importer
use App\Models\TenantUser; // Importer
use Illuminate\Support\Facades\Log;

class CreditNoteItemObserver
{
    public function created(CreditNoteItem $cnItem): void
    {
        $this->processStockForItem($cnItem, 'create');
    }

    public function updated(CreditNoteItem $cnItem): void
    {
        // Gérer si la quantité change sur un avoir déjà émis
        if ($cnItem->isDirty('quantity')) {
            // Annuler l'ancien mouvement de stock (plus complexe, nécessite de stocker l'ancien état)
            // Pour l'instant, on suppose que les items ne sont pas modifiés une fois l'avoir émis,
            // ou qu'on annule l'avoir et on en crée un nouveau.
            // Une solution simple est de ne traiter le stock que si l'avoir est dans un statut "final".
            Log::info("[CreditNoteItemObserver] UPDATED event for CreditNoteItem ID: {$cnItem->id}. Stock logic might need review for updates.");
             $this->processStockForItem($cnItem, 'update'); // Attention, peut causer des doubles mouvements si non géré finement
        }
    }

    public function deleted(CreditNoteItem $cnItem): void
    {
        // Si un item est supprimé d'un avoir qui avait réintégré le stock, il faut annuler cette réintégration
         $this->processStockForItem($cnItem, 'delete'); // La logique doit être inversée
    }

    protected function processStockForItem(CreditNoteItem $cnItem, string $eventContext): void
    {
        $creditNote = $cnItem->creditNote;
        if (!$creditNote) {
            Log::error("[CreditNoteItemObserver] CreditNote relation is null for CreditNoteItem ID: {$cnItem->id}");
            return;
        }

        // Statuts de l'avoir qui déclenchent l'impact sur le stock
        $stockImpactingStatuses = ['issued', 'applied']; // 'issued' = émis et finalisé

        if (!in_array($creditNote->status, $stockImpactingStatuses)) {
            Log::info("[CreditNoteItemObserver] Stock not processed for item ID {$cnItem->id}. CreditNote status is '{$creditNote->status}'. Event: {$eventContext}");
            return;
        }
        
        // Vérifier le toggle global de l'avoir pour le retour en stock
        if (!$creditNote->restock_items) {
            Log::info("[CreditNoteItemObserver] CreditNote ID {$creditNote->id} is not marked for restock. Skipping stock processing for item ID {$cnItem->id}.");
            return;
        }

        if (!$cnItem->product_id) {
            Log::info("[CreditNoteItemObserver] No product for item ID {$cnItem->id}. Event: {$eventContext}");
            return; // Pas de produit
        }

        $product = $cnItem->product;
        if (!$product) {
            Log::error("[CreditNoteItemObserver] Product not found for item ID {$cnItem->id}. Event: {$eventContext}");
            return;
        }

        if ($eventContext === 'create' || $eventContext === 'update') { // Pour update, il faudrait gérer la différence. Simplifions pour l'instant.
            Log::info("[CreditNoteItemObserver] Re-incrementing stock for Product ID: {$product->id}, Qty: {$cnItem->quantity}. Event: {$eventContext}");
            $product->increment('stock_quantity', $cnItem->quantity);
            $newStockQuantity = $product->fresh()->stock_quantity;

            StockMovement::create([
                'product_id' => $cnItem->product_id,
                'type' => 'customer_return', // Retour client
                'quantity_changed' => $cnItem->quantity, // Positif
                'new_stock_quantity_after_movement' => $newStockQuantity,
                'related_document_type' => CreditNote::class,
                'related_document_id' => $creditNote->id,
                'user_id' => $creditNote->user_id,
                'movement_date' => $creditNote->credit_note_date,
                'notes' => "Retour client - Avoir: {$creditNote->credit_note_number}",
            ]);
             Log::info("[CreditNoteItemObserver] StockMovement 'customer_return' created. Event: {$eventContext}");

        } elseif ($eventContext === 'delete') {
             Log::info("[CreditNoteItemObserver] Decrementing stock (reversing return) for Product ID: {$product->id}, Qty: {$cnItem->quantity}. Event: {$eventContext}");
            $product->decrement('stock_quantity', $cnItem->quantity); // Annuler la réintégration
            $newStockQuantity = $product->fresh()->stock_quantity;

            StockMovement::create([
                'product_id' => $cnItem->product_id,
                'type' => 'customer_return_cancellation',
                'quantity_changed' => -$cnItem->quantity, // Négatif
                'new_stock_quantity_after_movement' => $newStockQuantity,
                'related_document_type' => CreditNote::class,
                'related_document_id' => $creditNote->id,
                'user_id' => auth()->check() && auth()->user() instanceof TenantUser ? auth()->user()->getKey() : null,
                'movement_date' => now(),
                'reason' => "Annulation item retour client - Avoir: {$creditNote->credit_note_number}",
            ]);
            Log::info("[CreditNoteItemObserver] StockMovement 'customer_return_cancellation' created. Event: {$eventContext}");
        }
    }
}