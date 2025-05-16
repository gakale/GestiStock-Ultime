<?php

namespace App\Observers;

use App\Models\SupplierCreditNoteItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\SupplierCreditNote; // Importer
use App\Models\TenantUser;       // Importer
use Illuminate\Support\Facades\Log;

class SupplierCreditNoteItemObserver
{
    /**
     * Handle the SupplierCreditNoteItem "created" event.
     */
    public function created(SupplierCreditNoteItem $scnItem): void
    {
        $this->processStockForItem($scnItem, 'create');
    }

    /**
     * Handle the SupplierCreditNoteItem "updated" event.
     * Pour les avoirs fournisseurs, une mise à jour d'item sur un avoir confirmé
     * est plus délicate. Idéalement, on annule et on recrée.
     * Pour l'instant, on va logguer et ne pas agir si l'avoir est déjà confirmé,
     * sauf si c'est le statut de l'avoir lui-même qui change (géré par un autre observer/service).
     */
    public function updated(SupplierCreditNoteItem $scnItem): void
    {
        $supplierCreditNote = $scnItem->supplierCreditNote;
        // Si la quantité ou le produit change sur un item d'un avoir DÉJÀ confirmé, la logique est complexe.
        // Il faudrait annuler le mouvement précédent et en créer un nouveau.
        // Simplification: on ne traite que si l'item est modifié alors que l'avoir est encore en brouillon
        // ou si c'est le statut de l'avoir qui passe à confirmé (ceci sera géré au niveau de l'observer de SupplierCreditNote)
        if ($supplierCreditNote && $supplierCreditNote->status === 'draft' && ($scnItem->isDirty('quantity') || $scnItem->isDirty('product_id'))) {
             Log::info("[SupplierCreditNoteItemObserver] UPDATED event for DRAFT SCN Item ID: {$scnItem->id}. Stock not yet impacted.");
        } elseif ($supplierCreditNote && $supplierCreditNote->status === 'confirmed' && ($scnItem->isDirty('quantity') || $scnItem->isDirty('product_id'))) {
            Log::warning("[SupplierCreditNoteItemObserver] UPDATED event for CONFIRMED SCN Item ID: {$scnItem->id}. Manual stock adjustment might be needed or SCN should be cancelled and redone.");
            // Potentiellement, on pourrait appeler processStockForItem ici si on a une logique robuste pour les deltas.
            // $this->processStockForItem($scnItem, 'update_confirmed'); // Nécessiterait une gestion des deltas
        }
    }

    /**
     * Handle the SupplierCreditNoteItem "deleted" event.
     */
    public function deleted(SupplierCreditNoteItem $scnItem): void
    {
        // Si un item est supprimé d'un avoir confirmé, il faut inverser le mouvement de stock.
        $this->processStockForItem($scnItem, 'delete');
    }

    protected function processStockForItem(SupplierCreditNoteItem $scnItem, string $eventContext): void
    {
        $supplierCreditNote = $scnItem->supplierCreditNote()->first(); // Assurez-vous que la relation est chargée

        if (!$supplierCreditNote) {
            Log::error("[SupplierCreditNoteItemObserver] SupplierCreditNote relation is null for SCN Item ID: {$scnItem->id}. Event: {$eventContext}");
            return;
        }

        // Statuts de l'avoir qui déclenchent l'impact sur le stock (sortie de stock)
        $stockImpactingStatuses = ['confirmed']; // Uniquement quand l'avoir est finalisé

        // Si l'événement est 'delete', on doit agir même si le statut n'est plus 'confirmed' pour annuler un mouvement précédent.
        // Cependant, pour plus de sûreté, on ne traite l'annulation que si l'avoir *était* confirmé.
        // Une meilleure approche serait de vérifier si un mouvement de stock existe pour cet item et l'annuler.

        if ($eventContext !== 'delete' && !in_array($supplierCreditNote->status, $stockImpactingStatuses)) {
            Log::info("[SupplierCreditNoteItemObserver] Stock not processed for item ID {$scnItem->id}. SCN status is '{$supplierCreditNote->status}'. Event: {$eventContext}");
            return;
        }

        // Vérifier le toggle global de l'avoir pour le retour PHYSIQUE des articles
        if (!$supplierCreditNote->items_returned_to_supplier_stock) {
            Log::info("[SupplierCreditNoteItemObserver] SCN ID {$supplierCreditNote->id} is not marked for items_returned_to_supplier_stock. Skipping stock processing for item ID {$scnItem->id}.");
            return;
        }

        if (!$scnItem->product_id) {
            Log::info("[SupplierCreditNoteItemObserver] No product for SCN item ID {$scnItem->id}. Event: {$eventContext}");
            return;
        }

        $product = $scnItem->product()->first(); // Assurez-vous que la relation est chargée
        if (!$product) {
            Log::error("[SupplierCreditNoteItemObserver] Product not found for SCN item ID {$scnItem->id}. Event: {$eventContext}");
            return;
        }

        // Cas de la création d'item (typiquement quand l'avoir est encore 'draft', donc pas d'impact stock immédiat)
        // L'impact stock se fera lors de la confirmation de l'avoir.
        // Nous allons donc déplacer la logique principale vers un Observer sur SupplierCreditNote (pour l'événement 'updated' quand status passe à 'confirmed')

        if ($eventContext === 'create' && $supplierCreditNote->status !== 'confirmed') {
            Log::info("[SupplierCreditNoteItemObserver - CREATE] SCN Item ID: {$scnItem->id} created for SCN in status '{$supplierCreditNote->status}'. Stock movement deferred to SCN confirmation.");
            return; // Pas d'action sur le stock tant que l'avoir n'est pas confirmé
        }


        // Cas de la suppression d'item d'un avoir DÉJÀ confirmé
        if ($eventContext === 'delete') {
            // On ne peut agir que si l'avoir était confirmé ET que les items devaient être retournés
            // Cela suppose que la suppression d'item est possible sur un avoir confirmé, ce qui est discutable.
            // Idéalement, un avoir confirmé est immuable ou doit être annulé.
            // Si on permet la suppression, on doit annuler la sortie de stock.
            if ($supplierCreditNote->getOriginal('status') === 'confirmed' && $supplierCreditNote->getOriginal('items_returned_to_supplier_stock')) {
                Log::info("[SupplierCreditNoteItemObserver - DELETE] Incrementing stock (reversing supplier return) for Product ID: {$product->id}, Qty: {$scnItem->quantity}. SCN ID: {$supplierCreditNote->id}");
                $product->increment('stock_quantity', $scnItem->quantity); // Ré-incrémenter notre stock
                $newStockQuantity = $product->fresh()->stock_quantity;

                StockMovement::create([
                    'product_id' => $scnItem->product_id,
                    'type' => 'supplier_return_cancellation', // Annulation retour fournisseur
                    'quantity_changed' => $scnItem->quantity, // Positif pour nous
                    'new_stock_quantity_after_movement' => $newStockQuantity,
                    'related_document_type' => SupplierCreditNote::class,
                    'related_document_id' => $supplierCreditNote->id,
                    'user_id' => auth()->check() && auth()->user() instanceof TenantUser ? auth()->user()->getKey() : ($supplierCreditNote->user_id ?? null),
                    'movement_date' => now(),
                    'reason' => "Annulation item retour fournisseur - Avoir Four.: {$supplierCreditNote->credit_note_number}, Item ID: {$scnItem->id}",
                ]);
                Log::info("[SupplierCreditNoteItemObserver] StockMovement 'supplier_return_cancellation' created for deleted item.");
            } else {
                Log::info("[SupplierCreditNoteItemObserver - DELETE] No stock action for deleted item ID {$scnItem->id}. SCN original status was not 'confirmed' or items were not marked for return. SCN ID: {$supplierCreditNote->id}");
            }
        }
        // La logique de DECREMENTATION du stock pour un retour fournisseur
        // sera gérée par un SupplierCreditNoteObserver lorsque le statut de l'avoir passe à 'confirmed'.
    }
}