<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\CreditNoteItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\CreditNote;
use App\Models\TenantUser;
use App\Models\UnitOfMeasure;
use App\Services\UnitConversionService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

class CreditNoteItemObserver
{
    /**
     * Calcule la quantité à ajouter au stock en fonction de l'unité de transaction
     */
    protected function getStockQuantityChange(CreditNoteItem $cnItem): float
    {
        $product = $cnItem->product;
        $transactionUnit = UnitOfMeasure::find($cnItem->transaction_unit_id);

        if (!$product || !$transactionUnit || !$product->stockUnit || is_null($cnItem->quantity)) {
            Log::warning("Données manquantes pour la conversion de stock sur CreditNoteItem ID: {$cnItem->id}");
            return (float)$cnItem->quantity; // Utiliser la quantité telle quelle si pas de conversion possible
        }

        try {
            $conversionService = App::make(UnitConversionService::class);
            return $conversionService->convert(
                $product,
                (float)$cnItem->quantity, // Quantité dans l'unité de transaction
                $transactionUnit,              // Unité de transaction
                $product->stockUnit            // Unité de stock du produit (cible)
            );
        } catch (\Exception $e) {
            Log::error("Erreur de conversion pour CreditNoteItem {$cnItem->id}: " . $e->getMessage());
            return (float)$cnItem->quantity; // Utiliser la quantité telle quelle en cas d'erreur
        }
    }
    
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
            // Convertir la quantité de l'unité de transaction vers l'unité de stock
            $quantityInStockUnit = $this->getStockQuantityChange($cnItem);
            
            Log::info("[CreditNoteItemObserver] Re-incrementing stock for Product ID: {$product->id}, Qty: {$quantityInStockUnit}. Event: {$eventContext}");
            $product->increment('stock_quantity', $quantityInStockUnit);
            $newStockQuantity = $product->fresh()->stock_quantity;
            
            // Préparer les notes avec informations sur l'unité
            $notes = "Retour client - Avoir: {$creditNote->credit_note_number} - Article: {$product->name}";
            
            // Ajouter les informations d'unité de transaction si disponibles
            if ($cnItem->transaction_unit_id && $cnItem->quantity) {
                $transactionUnit = $cnItem->transactionUnit;
                $notes .= " ({$cnItem->quantity} {$transactionUnit?->symbol})";
                
                // Si l'unité de transaction est différente de l'unité de stock
                if ($product->stock_unit_id && $product->stock_unit_id != $cnItem->transaction_unit_id) {
                    $notes .= " (converti en {$quantityInStockUnit} {$product->stockUnit?->symbol})"; 
                }
            }

            StockMovement::create([
                'product_id' => $cnItem->product_id,
                'type' => 'customer_return', // Retour client
                'quantity_changed' => $quantityInStockUnit, // Positif
                'new_stock_quantity_after_movement' => $newStockQuantity,
                'related_document_type' => CreditNote::class,
                'related_document_id' => $creditNote->id,
                'user_id' => $creditNote->user_id,
                'movement_date' => $creditNote->credit_note_date,
                'notes' => $notes,
            ]);
             Log::info("[CreditNoteItemObserver] StockMovement 'customer_return' created. Event: {$eventContext}");

        } elseif ($eventContext === 'delete') {
            // Convertir la quantité de l'unité de transaction vers l'unité de stock
            $quantityInStockUnit = $this->getStockQuantityChange($cnItem);
            
            Log::info("[CreditNoteItemObserver] Decrementing stock (reversing return) for Product ID: {$product->id}, Qty: {$quantityInStockUnit}. Event: {$eventContext}");
            $product->decrement('stock_quantity', $quantityInStockUnit); // Annuler la réintégration
            $newStockQuantity = $product->fresh()->stock_quantity;
            
            // Préparer les notes avec informations sur l'unité
            $notes = "Annulation item retour client - Avoir: {$creditNote->credit_note_number} - Article: {$product->name}";
            
            // Ajouter les informations d'unité de transaction si disponibles
            if ($cnItem->transaction_unit_id && $cnItem->quantity) {
                $transactionUnit = $cnItem->transactionUnit;
                $notes .= " ({$cnItem->quantity} {$transactionUnit?->symbol})";
                
                // Si l'unité de transaction est différente de l'unité de stock
                if ($product->stock_unit_id && $product->stock_unit_id != $cnItem->transaction_unit_id) {
                    $notes .= " (converti en {$quantityInStockUnit} {$product->stockUnit?->symbol})"; 
                }
            }

            StockMovement::create([
                'product_id' => $cnItem->product_id,
                'type' => 'customer_return_cancellation',
                'quantity_changed' => -$quantityInStockUnit, // Négatif
                'new_stock_quantity_after_movement' => $newStockQuantity,
                'related_document_type' => CreditNote::class,
                'related_document_id' => $creditNote->id,
                'user_id' => auth()->check() && auth()->user() instanceof TenantUser ? auth()->user()->getKey() : null,
                'movement_date' => now(),
                'notes' => $notes,
            ]);
            Log::info("[CreditNoteItemObserver] StockMovement 'customer_return_cancellation' created. Event: {$eventContext}");
        }
    }
}