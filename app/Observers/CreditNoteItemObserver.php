<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\CreditNoteItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\CreditNote;
use App\Models\TenantUser;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

class CreditNoteItemObserver
{
    public function created(CreditNoteItem $cnItem): void
    {
        $this->processStockUpdate($cnItem, 'create');
        
        if ($cnItem->creditNote) {
            $cnItem->creditNote->calculateTotals();
        }
    }

    public function updated(CreditNoteItem $cnItem): void
    {
        $this->processStockUpdate($cnItem, 'update');

        if ($cnItem->creditNote) {
            $cnItem->creditNote->calculateTotals();
        }
    }

    public function deleted(CreditNoteItem $cnItem): void
    {
        $this->processStockUpdate($cnItem, 'delete');
        
        if ($cnItem->creditNote) {
            $cnItem->creditNote->calculateTotals();
        }
    }

    /**
     * Traite la mise à jour du stock pour un CreditNoteItem.
     * Gère la création, la mise à jour (avec ajustement différentiel) et la suppression.
     */
    protected function processStockUpdate(CreditNoteItem $cnItem, string $eventContext): void
    {
        $creditNote = CreditNote::find($cnItem->credit_note_id); // Recharger pour s'assurer qu'on a le dernier état

        if (!$creditNote) {
            Log::error("[CreditNoteItemObserver] CreditNote introuvable pour CreditNoteItem ID: {$cnItem->id}. CN ID: {$cnItem->credit_note_id}");
            return;
        }

        $stockImpactingStatuses = ['issued', 'applied']; // Statuts qui impactent le stock normalement
        
        // Contextes qui forcent le traitement du stock même si le statut actuel de la CN n'est pas 'stockImpacting'
        // Cela est utile quand CreditNoteObserver dicte une action basée sur un changement de statut (ex: issued -> voided)
        $forceStockProcessingContexts = [
            'delete_due_to_cn_status_change', // Pour annuler un impact stock quand CN passe de 'issued' à 'voided', etc.
            'create_due_to_cn_status_change'  // Pour appliquer un impact stock quand CN passe de 'draft' à 'issued'
        ];

        if (!in_array($creditNote->status, $stockImpactingStatuses) && !in_array($eventContext, $forceStockProcessingContexts)) {
            Log::info("[CreditNoteItemObserver] Le statut de l'avoir ({$creditNote->status}) ou le contexte ({$eventContext}) n'entraîne pas d'impact sur le stock pour l'item ID: {$cnItem->id}.");
            return;
        }
        
        // Si le contexte est forcé mais que la note de crédit n'est pas marquée pour restock_items (pour le cas create_due_to_cn_status_change)
        if ($eventContext === 'create_due_to_cn_status_change' && !$creditNote->restock_items) {
             Log::info("[CreditNoteItemObserver] CN #{$creditNote->credit_note_number} non marquée pour restock. Pas d'impact stock pour item ID: {$cnItem->id} via contexte {$eventContext}.");
             return;
        }

        $product = Product::find($cnItem->product_id); // Recharger le produit
        if (!$product) {
            Log::error("[CreditNoteItemObserver] Produit introuvable (ID: {$cnItem->product_id}) pour CreditNoteItem ID: {$cnItem->id}. Contexte: {$eventContext}");
            return;
        }

        // La quantité d'impact sur le stock est maintenant directement stock_unit_quantity
        // Il faut s'assurer qu'elle est calculée et disponible.
        $currentStockUnitQuantity = (float) $cnItem->stock_unit_quantity;

        if (is_null($cnItem->stock_unit_quantity)) {
             Log::warning("[CreditNoteItemObserver] stock_unit_quantity est null pour CreditNoteItem ID: {$cnItem->id}. Impossible de mettre à jour le stock. Contexte: {$eventContext}");
             return;
        }

        $quantityChangeForStock = 0;
        $stockMovementType = '';
        $notes = '';

        if ($eventContext === 'create' || $eventContext === 'create_due_to_cn_status_change') {
            // Ne traiter que si restock_items est vrai pour create_due_to_cn_status_change
            // Cette vérification est maintenant faite plus haut pour ce contexte spécifique.
            $quantityChangeForStock = $currentStockUnitQuantity;
            $stockMovementType = 'inventory_adjustment'; // Utiliser inventory_adjustment au lieu de customer_return
            $notes = "Retour client via Avoir #{$creditNote->credit_note_number} - Article: {$product->name} ({$cnItem->quantity} {$cnItem->transactionUnit?->symbol} => {$currentStockUnitQuantity} {$product->stockUnit?->symbol})";
            Log::info("[CreditNoteItemObserver] CONTEXT ({$eventContext}): Augmentation stock pour Produit ID {$product->id} de {$quantityChangeForStock}. Item ID: {$cnItem->id}");
        
        } elseif ($eventContext === 'update') {
            // Gérer la mise à jour est complexe car il faut l'ancienne et la nouvelle valeur.
            // On récupère la quantité originale de stock_unit_quantity.
            $originalStockUnitQuantity = (float) $cnItem->getOriginal('stock_unit_quantity');

            // Si le produit a changé, c'est plus complexe : annuler l'ancien, ajouter le nouveau.
            if ($cnItem->isDirty('product_id')) {
                $originalProduct = Product::find($cnItem->getOriginal('product_id'));
                if ($originalProduct) {
                    // Annuler l'impact sur l'ancien produit
                    $this->adjustProductStock($originalProduct, -$originalStockUnitQuantity, $creditNote, $cnItem, 'update_product_changed_old');
                }
                // Ajouter l'impact sur le nouveau produit
                $quantityChangeForStock = $currentStockUnitQuantity;
                $stockMovementType = 'inventory_adjustment'; // Utiliser inventory_adjustment au lieu de customer_return
                $notes = "Retour client (produit changé) Avoir #{$creditNote->credit_note_number} - Article: {$product->name} ({$cnItem->quantity} {$cnItem->transactionUnit?->symbol} => {$currentStockUnitQuantity} {$product->stockUnit?->symbol})";
                Log::info("[CreditNoteItemObserver] UPDATED (product changed): Stock ajusté. Ancien Produit ID {$originalProduct?->id}, Nouveau Produit ID {$product->id}. Item ID: {$cnItem->id}");
            } else {
                // Le produit n'a pas changé, on ajuste la différence de quantité
                $quantityChangeForStock = $currentStockUnitQuantity - $originalStockUnitQuantity;
                if ($quantityChangeForStock > 0) {
                    $stockMovementType = 'inventory_adjustment'; // Augmentation du retour
                    $notes = "Augmentation retour Avoir #{$creditNote->credit_note_number} - Article: {$product->name} ({$cnItem->quantity} {$cnItem->transactionUnit?->symbol} => {$currentStockUnitQuantity} {$product->stockUnit?->symbol}). Diff: {$quantityChangeForStock}";
                } elseif ($quantityChangeForStock < 0) {
                    $stockMovementType = 'inventory_adjustment'; // Diminution du retour
                    $notes = "Diminution retour Avoir #{$creditNote->credit_note_number} - Article: {$product->name} ({$cnItem->quantity} {$cnItem->transactionUnit?->symbol} => {$currentStockUnitQuantity} {$product->stockUnit?->symbol}). Diff: {$quantityChangeForStock}";
                } else {
                    // Aucune modification de quantité de stock, mais peut-être d'autres champs. Pas d'impact stock.
                    Log::info("[CreditNoteItemObserver] UPDATED: Aucune modification de stock_unit_quantity pour Produit ID {$product->id}. Item ID: {$cnItem->id}");
                    return; 
                }
                Log::info("[CreditNoteItemObserver] UPDATED: Ajustement stock pour Produit ID {$product->id} de {$quantityChangeForStock}. Item ID: {$cnItem->id}");
            }

        } elseif ($eventContext === 'delete' || $eventContext === 'delete_due_to_cn_status_change') {
            // Pour 'delete', $cnItem contient les valeurs au moment de la suppression.
            // Pour 'delete_due_to_cn_status_change', on annule l'impact précédent.
            $quantityChangeForStock = -$currentStockUnitQuantity; // Négatif pour annuler
            $stockMovementType = 'inventory_adjustment'; // Utiliser inventory_adjustment pour tous les types de mouvements
            $notes = "Annulation impact stock Avoir #{$creditNote->credit_note_number} - Article: {$product->name} ({$cnItem->quantity} {$cnItem->transactionUnit?->symbol} => {$currentStockUnitQuantity} {$product->stockUnit?->symbol}). Contexte: {$eventContext}";
            Log::info("[CreditNoteItemObserver] CONTEXT ({$eventContext}): Diminution stock (annulation) pour Produit ID {$product->id} de {$currentStockUnitQuantity}. Item ID: {$cnItem->id}");
        }

        if ($quantityChangeForStock != 0) {
            $this->adjustProductStock($product, $quantityChangeForStock, $creditNote, $cnItem, $stockMovementType, $notes);
        } else {
             Log::info("[CreditNoteItemObserver] Aucune modification de stock nécessaire pour Produit ID {$product->id}. Changement: {$quantityChangeForStock}. Item ID: {$cnItem->id}. Event: {$eventContext}");
        }
    }

    /**
     * Ajuste le stock du produit et crée un mouvement de stock.
     */
    protected function adjustProductStock(Product $product, float $quantityAdjustment, CreditNote $creditNote, CreditNoteItem $cnItem, string $movementType, string $customNotes = ''):
    void
    {
        if ($quantityAdjustment == 0) {
            return;
        }
        
        // Début de la transaction pour assurer l'atomicité
        // DB::beginTransaction(); // Si non géré par l'appelant

        try {
            // L'ajustement peut être positif (augmentation) ou négatif (diminution)
            if ($quantityAdjustment > 0) {
                $product->increment('stock_quantity', $quantityAdjustment);
            } else {
                $product->decrement('stock_quantity', abs($quantityAdjustment));
            }
            $newStockQuantity = $product->fresh()->stock_quantity;
            
            $defaultNotes = $customNotes ?: "Mouvement de stock pour Avoir #{$creditNote->credit_note_number}, Article #{$cnItem->id} ({$product->name})";

            StockMovement::create([
                'product_id' => $product->id,
                'type' => $movementType,
                'quantity_changed' => $quantityAdjustment,
                'new_stock_quantity_after_movement' => $newStockQuantity,
                'related_document_type' => CreditNote::class,
                'related_document_id' => $creditNote->id,
                'related_document_item_id' => $cnItem->id, // Lier au CreditNoteItem spécifique
                'user_id' => $creditNote->user_id ?? (auth()->check() && auth()->user() instanceof TenantUser ? auth()->user()->getKey() : null),
                'movement_date' => $creditNote->credit_note_date ?? now(),
                'notes' => $defaultNotes,
            ]);

            Log::info("[CreditNoteItemObserver.adjustProductStock] Stock ajusté pour Produit ID {$product->id} de {$quantityAdjustment}. Nouveau stock: {$newStockQuantity}. Type: {$movementType}");
            // DB::commit(); // Si non géré par l'appelant
        } catch (\Exception $e) {
            // DB::rollBack(); // Si non géré par l'appelant
            Log::critical("[CreditNoteItemObserver.adjustProductStock] Échec de l'ajustement du stock pour Produit ID {$product->id}: " . $e->getMessage());
            // Propager l'exception ou gérer l'erreur comme il se doit
            throw $e;
        }
    }
}