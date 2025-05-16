<?php

namespace App\Observers;

use App\Models\SupplierCreditNote;
use App\Models\StockMovement;
use App\Models\TenantUser;
use Illuminate\Support\Facades\Log;

class SupplierCreditNoteObserver
{
    /**
     * Handle the SupplierCreditNote "created" event.
     * Principalement pour gérer la création avec le statut 'confirmed'.
     */
    public function created(SupplierCreditNote $supplierCreditNote): void
    {
        // Si l'avoir est créé directement avec le statut 'confirmed', traiter les mouvements de stock
        if ($supplierCreditNote->status === 'confirmed' && $supplierCreditNote->items_returned_to_supplier_stock) {
            Log::info("[SupplierCreditNoteObserver] SCN ID: {$supplierCreditNote->id} created with status 'confirmed' and items_returned_to_supplier_stock is true. Processing stock decrease for all items.");
            
            // Nous devons attendre que la transaction soit terminée et que les items soient créés
            // Utiliser afterCommit pour s'assurer que les items sont bien enregistrés en base
            \Illuminate\Support\Facades\DB::afterCommit(function () use ($supplierCreditNote) {
                Log::info("[SupplierCreditNoteObserver] Transaction committed, processing stock movements for SCN ID: {$supplierCreditNote->id}");
                
                // Recharger l'avoir avec ses items pour s'assurer qu'ils sont disponibles
                $freshSupplierCreditNote = SupplierCreditNote::with('items.product')->find($supplierCreditNote->id);
                
                if ($freshSupplierCreditNote) {
                    Log::info("[SupplierCreditNoteObserver] Found SCN with ID: {$supplierCreditNote->id}, items count: " . $freshSupplierCreditNote->items->count());
                    
                    foreach ($freshSupplierCreditNote->items as $item) {
                        $product = $item->product;
                        if ($product) {
                            try {
                                Log::info("[SupplierCreditNoteObserver] Decrementing stock for Product ID: {$product->id}, Qty: {$item->quantity}. SCN: {$freshSupplierCreditNote->credit_note_number}");
                                $product->decrement('stock_quantity', $item->quantity); // Sortie de notre stock
                                $newStockQuantity = $product->fresh()->stock_quantity;
                                Log::info("[SupplierCreditNoteObserver] New stock quantity: {$newStockQuantity}");

                                // Vérifier si le modèle StockMovement existe et a les bonnes colonnes
                                Log::info("[SupplierCreditNoteObserver] Attempting to create StockMovement");
                                
                                $stockMovement = StockMovement::create([
                                    'product_id' => $item->product_id,
                                    'type' => 'supplier_return', // Retour fournisseur
                                    'quantity_changed' => -$item->quantity, // Négatif pour nous
                                    'new_stock_quantity_after_movement' => $newStockQuantity,
                                    'related_document_type' => SupplierCreditNote::class,
                                    'related_document_id' => $freshSupplierCreditNote->id,
                                    'user_id' => $freshSupplierCreditNote->user_id, // User qui a confirmé l'avoir
                                    'movement_date' => $freshSupplierCreditNote->credit_note_date ?? now(),
                                    'reason' => "Retour fournisseur - Avoir Four.: {$freshSupplierCreditNote->credit_note_number}",
                                    'notes' => "Item: {$item->description}, Qty: {$item->quantity}",
                                ]);
                                Log::info("[SupplierCreditNoteObserver] StockMovement 'supplier_return' created for item ID {$item->id}.");
                            } catch (\Exception $e) {
                                Log::error("[SupplierCreditNoteObserver] Error creating stock movement: " . $e->getMessage());
                                Log::error("[SupplierCreditNoteObserver] Error trace: " . $e->getTraceAsString());
                            }
                        } else {
                            Log::error("[SupplierCreditNoteObserver] Product not found for SCN Item ID: {$item->id} during SCN creation.");
                        }
                    }
                } else {
                    Log::error("[SupplierCreditNoteObserver] Could not find SCN with ID: {$supplierCreditNote->id} for stock processing after commit.");
                }
            });
        }
    }

    /**
     * Handle the SupplierCreditNote "updated" event.
     * Principalement pour gérer le passage du statut à 'confirmed'.
     */
    public function updated(SupplierCreditNote $supplierCreditNote): void
    {
        // Si le statut passe à 'confirmed' ET que les items doivent être retournés
        if ($supplierCreditNote->isDirty('status') && $supplierCreditNote->status === 'confirmed') {
            if ($supplierCreditNote->items_returned_to_supplier_stock) {
                Log::info("[SupplierCreditNoteObserver] SCN ID: {$supplierCreditNote->id} status changed to 'confirmed' and items_returned_to_supplier_stock is true. Processing stock decrease for all items.");
                foreach ($supplierCreditNote->items as $item) {
                    $product = $item->product;
                    if ($product) {
                        try {
                            Log::info("[SupplierCreditNoteObserver] Decrementing stock for Product ID: {$product->id}, Qty: {$item->quantity}. SCN: {$supplierCreditNote->credit_note_number}");
                            $product->decrement('stock_quantity', $item->quantity); // Sortie de notre stock
                            $newStockQuantity = $product->fresh()->stock_quantity;
                            Log::info("[SupplierCreditNoteObserver] New stock quantity: {$newStockQuantity}");

                            // Vérifier si le modèle StockMovement existe et a les bonnes colonnes
                            Log::info("[SupplierCreditNoteObserver] Attempting to create StockMovement");
                            
                            $stockMovement = StockMovement::create([
                            'product_id' => $item->product_id,
                            'type' => 'supplier_return', // Retour fournisseur
                            'quantity_changed' => -$item->quantity, // Négatif pour nous
                            'new_stock_quantity_after_movement' => $newStockQuantity,
                            'related_document_type' => SupplierCreditNote::class,
                            'related_document_id' => $supplierCreditNote->id,
                            'user_id' => $supplierCreditNote->user_id, // User qui a confirmé l'avoir
                            'movement_date' => $supplierCreditNote->credit_note_date ?? now(),
                            'reason' => "Retour fournisseur - Avoir Four.: {$supplierCreditNote->credit_note_number}",
                            'notes' => "Item: {$item->description}, Qty: {$item->quantity}",
                        ]);
                            Log::info("[SupplierCreditNoteObserver] StockMovement 'supplier_return' created for item ID {$item->id}.");
                        } catch (\Exception $e) {
                            Log::error("[SupplierCreditNoteObserver] Error creating stock movement: " . $e->getMessage());
                            Log::error("[SupplierCreditNoteObserver] Error trace: " . $e->getTraceAsString());
                        }
                    } else {
                        Log::error("[SupplierCreditNoteObserver] Product not found for SCN Item ID: {$item->id} during SCN confirmation.");
                    }
                }
            } else {
                Log::info("[SupplierCreditNoteObserver] SCN ID: {$supplierCreditNote->id} status changed to 'confirmed' but items_returned_to_supplier_stock is false. No stock movement.");
            }
        }
        // Gérer le passage à 'cancelled' depuis 'confirmed'
        elseif ($supplierCreditNote->isDirty('status') && $supplierCreditNote->status === 'cancelled' && $supplierCreditNote->getOriginal('status') === 'confirmed') {
            if ($supplierCreditNote->getOriginal('items_returned_to_supplier_stock')) { // Si le stock avait été impacté
                Log::info("[SupplierCreditNoteObserver] SCN ID: {$supplierCreditNote->id} status changed to 'cancelled' from 'confirmed'. Reversing stock movements.");
                foreach ($supplierCreditNote->items as $item) { // Utiliser les items actuels, s'ils n'ont pas été supprimés
                    $product = $item->product;
                    if ($product) {
                        Log::info("[SupplierCreditNoteObserver] Incrementing stock (reversing supplier return) for Product ID: {$product->id}, Qty: {$item->quantity}. SCN: {$supplierCreditNote->credit_note_number}");
                        $product->increment('stock_quantity', $item->quantity); // Ré-incrémenter notre stock
                        $newStockQuantity = $product->fresh()->stock_quantity;

                        StockMovement::create([
                            'product_id' => $item->product_id,
                            'type' => 'supplier_return_cancellation_scn', // Annulation retour fournisseur (SCN complet)
                            'quantity_changed' => $item->quantity, // Positif pour nous
                            'new_stock_quantity_after_movement' => $newStockQuantity,
                            'related_document_type' => SupplierCreditNote::class,
                            'related_document_id' => $supplierCreditNote->id,
                            'user_id' => auth()->check() && auth()->user() instanceof TenantUser ? auth()->user()->getKey() : ($supplierCreditNote->user_id ?? null),
                            'movement_date' => now(),
                            'reason' => "Annulation Avoir Fournisseur: {$supplierCreditNote->credit_note_number}",
                            'notes' => "Item: {$item->description}, Qty: {$item->quantity}",
                        ]);
                        Log::info("[SupplierCreditNoteObserver] StockMovement 'supplier_return_cancellation_scn' created for item ID {$item->id}.");
                    }
                }
            } else {
                 Log::info("[SupplierCreditNoteObserver] SCN ID: {$supplierCreditNote->id} cancelled, but original items_returned_to_supplier_stock was false. No stock reversal needed.");
            }
        }
    }

    /**
     * Handle the SupplierCreditNote "deleting" event.
     * Si on supprime un avoir confirmé, il faut annuler les mouvements de stock.
     * Il est souvent préférable d'annuler (statut 'cancelled') plutôt que de supprimer.
     */
    public function deleting(SupplierCreditNote $supplierCreditNote): void
    {
        if ($supplierCreditNote->status === 'confirmed' && $supplierCreditNote->items_returned_to_supplier_stock) {
            Log::warning("[SupplierCreditNoteObserver] DELETING confirmed SCN ID: {$supplierCreditNote->id} that impacted stock. Reversing stock movements.");
            // La logique ici serait similaire à celle de l'annulation
            // On pourrait appeler une méthode partagée.
            // Attention: les items pourraient déjà être supprimés si la suppression cascade.
            // Il faudrait récupérer les items avant leur suppression ou stocker l'info ailleurs.
            // Pour cette raison, privilégier le passage à 'cancelled'.
            // Si la suppression est permise, il faut s'assurer de la logique de réintégration du stock ici.
            // Exemple simplifié (peut ne pas fonctionner si les items sont déjà partis à cause de la cascade)
            // foreach ($supplierCreditNote->items()->withTrashed()->get() as $item) { ... }

             Log::error("[SupplierCreditNoteObserver] Deleting a confirmed SCN that impacted stock is not fully handled robustly for stock reversal. Consider using 'cancelled' status instead.");
        }
    }
}