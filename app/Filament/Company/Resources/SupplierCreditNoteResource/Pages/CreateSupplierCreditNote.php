<?php

namespace App\Filament\Company\Resources\SupplierCreditNoteResource\Pages;

use App\Filament\Company\Resources\SupplierCreditNoteResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Log;

class CreateSupplierCreditNote extends CreateRecord
{
    protected static string $resource = SupplierCreditNoteResource::class;
    
    /**
     * Méthode appelée après la création de l'avoir fournisseur
     * Gère les mouvements de stock si l'avoir est créé avec le statut 'confirmed'
     */
    protected function afterCreate(): void
    {
        // Récupérer l'avoir qui vient d'être créé
        $supplierCreditNote = $this->record;
        
        // Vérifier si l'avoir est créé avec le statut 'confirmed' et que les articles doivent être retournés
        if ($supplierCreditNote->status === 'confirmed' && $supplierCreditNote->items_returned_to_supplier_stock) {
            Log::info("[CreateSupplierCreditNote] SCN ID: {$supplierCreditNote->id} created with status 'confirmed' and items_returned_to_supplier_stock is true. Processing stock decrease for all items.");
            
            // Recharger l'avoir avec ses items pour s'assurer qu'ils sont disponibles
            $supplierCreditNote->load('items.product');
            
            Log::info("[CreateSupplierCreditNote] Found SCN with ID: {$supplierCreditNote->id}, items count: " . $supplierCreditNote->items->count());
            
            foreach ($supplierCreditNote->items as $item) {
                $product = $item->product;
                if ($product) {
                    try {
                        Log::info("[CreateSupplierCreditNote] Decrementing stock for Product ID: {$product->id}, Qty: {$item->quantity}. SCN: {$supplierCreditNote->credit_note_number}");
                        $product->decrement('stock_quantity', $item->quantity); // Sortie de notre stock
                        $newStockQuantity = $product->fresh()->stock_quantity;
                        Log::info("[CreateSupplierCreditNote] New stock quantity: {$newStockQuantity}");

                        // Créer le mouvement de stock
                        Log::info("[CreateSupplierCreditNote] Attempting to create StockMovement");
                        
                        $stockMovement = StockMovement::create([
                            'product_id' => $item->product_id,
                            'type' => 'supplier_return', // Retour fournisseur
                            'quantity_changed' => -$item->quantity, // Négatif pour nous
                            'new_stock_quantity_after_movement' => $newStockQuantity,
                            'related_document_type' => get_class($supplierCreditNote),
                            'related_document_id' => $supplierCreditNote->id,
                            'user_id' => $supplierCreditNote->user_id, // User qui a créé l'avoir
                            'movement_date' => $supplierCreditNote->credit_note_date ?? now(),
                            'reason' => "Retour fournisseur - Avoir Four.: {$supplierCreditNote->credit_note_number}",
                            'notes' => "Item: {$item->description}, Qty: {$item->quantity}",
                        ]);
                        Log::info("[CreateSupplierCreditNote] StockMovement 'supplier_return' created for item ID {$item->id}.");
                    } catch (\Exception $e) {
                        Log::error("[CreateSupplierCreditNote] Error creating stock movement: " . $e->getMessage());
                        Log::error("[CreateSupplierCreditNote] Error trace: " . $e->getTraceAsString());
                    }
                } else {
                    Log::error("[CreateSupplierCreditNote] Product not found for SCN Item ID: {$item->id} during SCN creation.");
                }
            }
        }
    }
}
