<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\InvoiceItem;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\SalesOrderItem;
use App\Models\StockMovement;
use App\Models\UnitOfMeasure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class InvoiceItemObserver
{
    /**
     * La logique d'impact sur le stock est mieux gérée lorsque la facture parente change de statut.
     * Cette méthode statique sera appelée par InvoiceObserver.
     */
    public static function handleInvoiceStatusChange(InvoiceItem $item, Invoice $invoice): void
    {
        if (!$item->product_id || is_null($item->stock_unit_quantity) || $item->stock_unit_quantity == 0) {
            Log::info("[InvoiceItemObserver STATIC] Item {$item->id} sur Facture {$invoice->invoice_number} skippé: pas de produit ou qté stock nulle.");
            return;
        }

        $product = Product::find($item->product_id);
        if (!$product) {
            Log::error("[InvoiceItemObserver STATIC] Produit non trouvé pour item {$item->id} sur Facture {$invoice->invoice_number}.");
            return;
        }

        // Statuts de la facture qui déclenchent une sortie de stock
        $stockDecreasingStatuses = ['issued', 'sent', 'partially_paid', 'paid']; // Adaptez selon votre workflow
        // Statuts qui pourraient annuler une sortie de stock (si la facture est annulée APRES avoir impacté le stock)
        $stockReversingStatusesOnCancel = ['issued', 'sent', 'partially_paid', 'paid'];


        if (in_array($invoice->status, $stockDecreasingStatuses) &&
            (!in_array($invoice->getOriginal('status'), $stockDecreasingStatuses) || $item->wasChanged('stock_unit_quantity')) // Nouvelle émission OU qté modifiée sur facture déjà émise
           ) {
            DB::transaction(function () use ($product, $item, $invoice, $stockDecreasingStatuses) {
                $quantityToDecrease = (float)$item->stock_unit_quantity;
                
                // Si l'item a été mis à jour sur une facture déjà émise, calculer le delta
                if (in_array($invoice->getOriginal('status'), $stockDecreasingStatuses) && $item->wasChanged('stock_unit_quantity')) {
                    $oldStockUnitQuantity = (float)($item->getOriginal('stock_unit_quantity') ?? 0);
                    $quantityToDecrease = (float)$item->stock_unit_quantity - $oldStockUnitQuantity;
                }

                if ($quantityToDecrease == 0) return; // Pas de changement net de quantité

                Log::info("[InvoiceItemObserver STATIC] Décrémentation stock pour Produit ID: {$product->id}, Qté Stock (convertie): {$quantityToDecrease} pour Facture {$invoice->invoice_number}.");
                $product->decrement('stock_quantity', $quantityToDecrease);

                StockMovement::create([
                    'product_id' => $item->product_id,
                    'type' => 'sale_delivery',
                    'quantity_changed' => -$quantityToDecrease, // Négatif
                    'new_stock_quantity_after_movement' => $product->fresh()->stock_quantity,
                    'related_document_type' => Invoice::class,
                    'related_document_id' => $invoice->id,
                    'user_id' => $invoice->user_id,
                    'movement_date' => $invoice->invoice_date,
                    'transaction_unit_id' => $item->transaction_unit_id,
                    'notes' => "Vente Facture: {$invoice->invoice_number} - Article: {$product->name} ({$item->quantity} {$item->transactionUnit?->symbol})",
                ]);
            });
        } elseif ($invoice->status === 'cancelled' && in_array($invoice->getOriginal('status'), $stockReversingStatusesOnCancel)) {
            // Annulation d'une facture qui avait impacté le stock
            DB::transaction(function () use ($product, $item, $invoice, $stockReversingStatusesOnCancel) {
                // On utilise la quantité qui avait été déduite, stockée dans stock_unit_quantity
                $quantityToRevert = (float)($item->getOriginal('stock_unit_quantity') ?? $item->stock_unit_quantity ?? 0);
                if ($quantityToRevert == 0) return;

                Log::info("[InvoiceItemObserver STATIC] Annulation (réintégration stock) pour Produit ID: {$product->id}, Qté Stock (convertie): {$quantityToRevert} pour Facture {$invoice->invoice_number}.");
                $product->increment('stock_quantity', $quantityToRevert);

                StockMovement::create([
                    'product_id' => $item->product_id,
                    'type' => 'sale_cancellation',
                    'quantity_changed' => $quantityToRevert, // Positif
                    'new_stock_quantity_after_movement' => $product->fresh()->stock_quantity,
                    'related_document_type' => Invoice::class,
                    'related_document_id' => $invoice->id,
                    'user_id' => auth()->id() ?? $invoice->user_id,
                    'movement_date' => now(),
                    'transaction_unit_id' => $item->transaction_unit_id,
                    'notes' => "Annulation Facture: {$invoice->invoice_number} - Article: {$product->name}, Qté annulée: {$quantityToRevert} {$product->stockUnit?->symbol}",
                ]);
            });
        }
    }

    // Les méthodes created, updated, deleted de l'item ne font plus rien directement sur le stock.
    // C'est l'observer de Invoice qui déclenchera la logique via handleInvoiceStatusChange.
    public function created(InvoiceItem $invoiceItem): void 
    {
        // Si la facture est liée à un sales_order_item (via un sales_order_id sur Invoice, puis lien des items)
        if ($invoiceItem->sales_order_item_id) {
            $soItem = SalesOrderItem::find($invoiceItem->sales_order_item_id);
            if ($soItem) {
                $soItem->increment('quantity_invoiced', $invoiceItem->quantity);
                $soItem->salesOrder?->checkAndUpdateStatus();
                Log::info("[InvoiceItemObserver] Updated SalesOrderItem ID: {$soItem->id}, incremented quantity_invoiced by {$invoiceItem->quantity}");
            }
        }
    }
    
    public function updated(InvoiceItem $invoiceItem): void {}
    
    public function deleted(InvoiceItem $invoiceItem): void 
    {
        // Si un item est supprimé d'une facture qui AVAIT impacté le stock, il faut annuler ce mouvement spécifique.
        $invoice = $invoiceItem->invoice()->first();
        $product = Product::find($invoiceItem->getOriginal('product_id')); // Produit avant suppression de l'item
        $originalStockUnitQuantity = (float)($invoiceItem->getOriginal('stock_unit_quantity') ?? 0);

        if ($invoice && $product && $originalStockUnitQuantity != 0 && in_array($invoice->status, ['issued', 'sent', 'partially_paid', 'paid'])) {
             Log::warning("[InvoiceItemObserver DELETED] Item {$invoiceItem->id} supprimé de la facture émise {$invoice->invoice_number}. Annulation du mouvement de stock pour {$originalStockUnitQuantity} {$product->stockUnit?->symbol} du produit {$product->name}.");
             DB::transaction(function () use ($product, $originalStockUnitQuantity, $invoice, $invoiceItem) {
                $product->increment('stock_quantity', $originalStockUnitQuantity); // Réintégration
                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'sale_item_deleted', // Nouveau type
                    'quantity_changed' => $originalStockUnitQuantity,
                    'new_stock_quantity_after_movement' => $product->fresh()->stock_quantity,
                    'related_document_type' => Invoice::class,
                    'related_document_id' => $invoice->id,
                    'user_id' => auth()->id() ?? $invoice->user_id,
                    'movement_date' => now(),
                    'transaction_unit_id' => $invoiceItem->getOriginal('transaction_unit_id'),
                    'notes' => "Suppression item facture émise: {$invoice->invoice_number} - Article (supprimé): " . ($invoiceItem->getOriginal('description') ?: $product->name) . ", Qté annulée: {$originalStockUnitQuantity} {$product->stockUnit?->symbol}",
                ]);
             });
        }
        
        // Si la facture est liée à un sales_order_item
        if ($invoiceItem->sales_order_item_id) {
            $soItem = SalesOrderItem::find($invoiceItem->sales_order_item_id);
            if ($soItem) {
                $soItem->decrement('quantity_invoiced', $invoiceItem->quantity);
                $soItem->salesOrder?->checkAndUpdateStatus();
                Log::info("[InvoiceItemObserver] Updated SalesOrderItem ID: {$soItem->id}, decremented quantity_invoiced by {$invoiceItem->quantity}");
            }
        }
    }

    public function restored(InvoiceItem $invoiceItem): void {}
    
    public function forceDeleted(InvoiceItem $invoiceItem): void {}
}
