<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\SalesOrderItem;
use App\Models\StockMovement;
use App\Models\UnitOfMeasure;
use App\Services\UnitConversionService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

class InvoiceItemObserver
{
    /**
     * Handle the InvoiceItem "created" event.
     */
    /**
     * Calcule la quantité à déduire du stock en fonction de l'unité de transaction
     */
    protected function getStockQuantityChange(InvoiceItem $invoiceItem): float
    {
        $product = $invoiceItem->product;
        $transactionUnit = UnitOfMeasure::find($invoiceItem->transaction_unit_id);

        if (!$product || !$transactionUnit || !$product->stockUnit || is_null($invoiceItem->quantity)) {
            Log::warning("Données manquantes pour la conversion de stock sur InvoiceItem ID: {$invoiceItem->id}");
            return 0; // Ou lever une exception
        }

        try {
            $conversionService = App::make(UnitConversionService::class);
            return $conversionService->convert(
                $product,
                (float)$invoiceItem->quantity, // Quantité dans l'unité de transaction
                $transactionUnit,              // Unité de transaction
                $product->stockUnit            // Unité de stock du produit (cible)
            );
        } catch (\Exception $e) {
            Log::error("Erreur de conversion pour InvoiceItem {$invoiceItem->id}: " . $e->getMessage());
            throw $e; // Relancer pour que l'opération échoue et soit corrigée
        }
    }
    
    public function created(InvoiceItem $invoiceItem): void
    {
        Log::info("[InvoiceItemObserver] CREATED event for InvoiceItem ID: {$invoiceItem->id}. Parent Invoice status: " . $invoiceItem->invoice?->status);
        
        // Si la facture est liée à un sales_order_item (via un sales_order_id sur Invoice, puis lien des items)
        if ($invoiceItem->sales_order_item_id) {
            $soItem = SalesOrderItem::find($invoiceItem->sales_order_item_id);
            if ($soItem) {
                $soItem->increment('quantity_invoiced', $invoiceItem->quantity);
                $soItem->salesOrder?->checkAndUpdateStatus();
                Log::info("[InvoiceItemObserver] Updated SalesOrderItem ID: {$soItem->id}, incremented quantity_invoiced by {$invoiceItem->quantity}");
            }
        }
        
        // Gérer le mouvement de stock si la facture est émise
        if ($invoiceItem->invoice && $invoiceItem->invoice->status === 'issued') {
            $product = $invoiceItem->product;
            if ($product) {
                $quantityInStockUnit = $this->getStockQuantityChange($invoiceItem);
                if ($quantityInStockUnit > 0) {
                    // Décrémenter le stock du produit
                    $product->decrement('stock_quantity', $quantityInStockUnit);
                    
                    // Créer un mouvement de stock
                    $notes = "Vente Facture: {$invoiceItem->invoice->invoice_number} - Article: {$product->name}";
                    
                    // Ajouter les informations d'unité de transaction si disponibles
                    if ($invoiceItem->transaction_unit_id && $invoiceItem->quantity) {
                        $transactionUnit = $invoiceItem->transactionUnit;
                        $notes .= " ({$invoiceItem->quantity} {$transactionUnit?->symbol})";
                        
                        // Si l'unité de transaction est différente de l'unité de stock
                        if ($product->stock_unit_id && $product->stock_unit_id != $invoiceItem->transaction_unit_id) {
                            $notes .= " (converti en {$quantityInStockUnit} {$product->stockUnit?->symbol})"; 
                        }
                    }
                    
                    StockMovement::create([
                        'product_id' => $invoiceItem->product_id,
                        'type' => 'sale_delivery',
                        'quantity_changed' => -$quantityInStockUnit, // Négatif car sortie de stock
                        'new_stock_quantity_after_movement' => $product->fresh()->stock_quantity,
                        'related_document_type' => get_class($invoiceItem->invoice),
                        'related_document_id' => $invoiceItem->invoice_id,
                        'user_id' => $invoiceItem->invoice->created_by_user_id,
                        'movement_date' => $invoiceItem->invoice->invoice_date,
                        'notes' => $notes,
                    ]);
                    
                    Log::info("[InvoiceItemObserver] Stock décrémenté pour le produit ID: {$product->id} de {$quantityInStockUnit} unités");
                }
            }
        }
    }

    /**
     * Handle the InvoiceItem "updated" event.
     */
    public function updated(InvoiceItem $invoiceItem): void
    {
        //
    }

    /**
     * Handle the InvoiceItem "deleted" event.
     */
    public function deleted(InvoiceItem $invoiceItem): void
    {
        Log::info("[InvoiceItemObserver] DELETED event for InvoiceItem ID: {$invoiceItem->id}.");
        
        // Si la facture est liée à un sales_order_item
        if ($invoiceItem->sales_order_item_id) {
            $soItem = SalesOrderItem::find($invoiceItem->sales_order_item_id);
            if ($soItem) {
                $soItem->decrement('quantity_invoiced', $invoiceItem->quantity);
                $soItem->salesOrder?->checkAndUpdateStatus();
                Log::info("[InvoiceItemObserver] Updated SalesOrderItem ID: {$soItem->id}, decremented quantity_invoiced by {$invoiceItem->quantity}");
            }
        }
        
        // Gérer le mouvement de stock si la facture était émise
        if ($invoiceItem->invoice && $invoiceItem->invoice->status === 'issued') {
            $product = $invoiceItem->product;
            if ($product) {
                $quantityInStockUnit = $this->getStockQuantityChange($invoiceItem);
                if ($quantityInStockUnit > 0) {
                    // Incrémenter le stock du produit (annulation de la sortie)
                    $product->increment('stock_quantity', $quantityInStockUnit);
                    
                    // Créer un mouvement de stock d'annulation
                    $notes = "Annulation Facture: {$invoiceItem->invoice->invoice_number} - Article: {$product->name}";
                    
                    // Ajouter les informations d'unité de transaction si disponibles
                    if ($invoiceItem->transaction_unit_id && $invoiceItem->quantity) {
                        $transactionUnit = $invoiceItem->transactionUnit;
                        $notes .= " ({$invoiceItem->quantity} {$transactionUnit?->symbol})";
                        
                        // Si l'unité de transaction est différente de l'unité de stock
                        if ($product->stock_unit_id && $product->stock_unit_id != $invoiceItem->transaction_unit_id) {
                            $notes .= " (converti en {$quantityInStockUnit} {$product->stockUnit?->symbol})"; 
                        }
                    }
                    
                    StockMovement::create([
                        'product_id' => $invoiceItem->product_id,
                        'type' => 'sale_cancellation',
                        'quantity_changed' => $quantityInStockUnit, // Positif car retour en stock
                        'new_stock_quantity_after_movement' => $product->fresh()->stock_quantity,
                        'related_document_type' => get_class($invoiceItem->invoice),
                        'related_document_id' => $invoiceItem->invoice_id,
                        'user_id' => auth()->id(),
                        'movement_date' => now(),
                        'notes' => $notes,
                    ]);
                    
                    Log::info("[InvoiceItemObserver] Stock incrémenté pour le produit ID: {$product->id} de {$quantityInStockUnit} unités");
                }
            }
        }
    }

    /**
     * Handle the InvoiceItem "restored" event.
     */
    public function restored(InvoiceItem $invoiceItem): void
    {
        //
    }

    /**
     * Handle the InvoiceItem "force deleted" event.
     */
    public function forceDeleted(InvoiceItem $invoiceItem): void
    {
        //
    }
}
