<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Models\InvoiceItem;
// use App\Models\Product; // Commenté car plus utilisé
// use App\Models\StockMovement; // Commenté car plus utilisé
// use App\Models\TenantUser; // Commenté car plus utilisé
use Illuminate\Support\Facades\Log;

class InvoiceObserver
{
    /**
     * Handle the Invoice "saving" event.
     * Se déclenche avant que la facture ne soit sauvegardée (création ou mise à jour).
     */
    public function saving(Invoice $invoice): void
    {
        // S'assurer que les totaux sont recalculés avant chaque sauvegarde
        // La méthode calculateTotals sur le modèle Invoice peut être appelée ici
        // ou directement dans le contrôleur/action Filament avant la sauvegarde
        // pour s'assurer que les items sont déjà persistés.
        // Pour l'instant, on suppose que calculateTotals est appelé par InvoiceItemObserver
        // ou par la logique du formulaire Filament.
    }


    /**
     * Handle the Invoice "updated" event.
     * Se déclenche après qu'une facture a été mise à jour.
     */
    public function updated(Invoice $invoice): void
    {
        // $this->processStockForInvoice($invoice, 'update'); // On ne gère plus le stock ici
        Log::info("[InvoiceObserver] UPDATED event for Invoice ID: {$invoice->id}, Status: {$invoice->status}");
        // Vous pourriez garder une logique ici si le statut de la facture doit déclencher autre chose
    }

    /**
     * Handle the Invoice "created" event.
     * Se déclenche après qu'une facture a été créée.
     */
    public function created(Invoice $invoice): void
    {
        // $this->processStockForInvoice($invoice, 'create'); // On ne gère plus le stock ici
        Log::info("[InvoiceObserver] CREATED event for Invoice ID: {$invoice->id}, Status: {$invoice->status}");
    }

    /**
     * Logique pour traiter les mouvements de stock pour une facture.
     * COMMENTÉ : Cette méthode n'est plus utilisée car la gestion du stock est déplacée vers les bons de livraison
     */
    /*
    protected function processStockForInvoice(Invoice $invoice, string $eventContext): void
    {
        Log::info("[InvoiceObserver] Processing stock for Invoice ID: {$invoice->id}, Status: {$invoice->status}, Event: {$eventContext}");

        // Définir les statuts qui déclenchent la sortie de stock
        $stockDeductingStatuses = ['sent', 'paid', 'overdue', 'confirmed']; // 'confirmed' si vous ajoutez ce statut

        // Vérifier si le statut actuel est un statut de déduction de stock
        $shouldDeductStock = in_array($invoice->status, $stockDeductingStatuses);

        // Vérifier si le statut *précédent* était un statut de déduction (pour les annulations/retours)
        // getOriginal() retourne les attributs avant la sauvegarde la plus récente
        $wasPreviouslyDeductingStock = false;
        if ($invoice->wasChanged('status')) {
            $previousStatus = $invoice->getOriginal('status');
            $wasPreviouslyDeductingStock = in_array($previousStatus, $stockDeductingStatuses);
            Log::info("[InvoiceObserver] Invoice status changed from '{$previousStatus}' to '{$invoice->status}'.");
        }


        foreach ($invoice->items()->with('product')->get() as $item) {
            if (!$item->product) {
                Log::warning("[InvoiceObserver] Product not found for InvoiceItem ID: {$item->id}, Product ID: {$item->product_id}");
                continue;
            }

            // Cas 1: La facture passe à un statut où le stock DOIT être déduit
            // ET elle n'était PAS auparavant dans un statut où le stock était déjà déduit.
            if ($shouldDeductStock && !$wasPreviouslyDeductingStock) {
                Log::info("[InvoiceObserver] Deducting stock for item: {$item->product->name}, Qty: {$item->quantity}");
                $this->deductStockAndCreateMovement($item, $invoice);
            }
            // Cas 2: La facture était dans un statut où le stock était déduit,
            // ET elle passe maintenant à un statut où le stock ne DOIT PLUS être déduit (ex: brouillon, annulée).
            elseif (!$shouldDeductStock && $wasPreviouslyDeductingStock) {
                Log::info("[InvoiceObserver] Re-incrementing stock for item: {$item->product->name}, Qty: {$item->quantity}");
                $this->reIncrementStockAndCreateMovement($item, $invoice);
            }
            // Cas 3: La facture RESTE dans un statut où le stock est déduit, mais un item a pu changer (quantité).
            // Ceci est plus complexe et nécessiterait de stocker la quantité précédemment déduite pour cet item
            // ou de recalculer tous les mouvements. Pour l'instant, on se concentre sur les transitions de statut.
            // Si vous modifiez une facture 'sent' et changez la quantité d'un item, l'événement 'updated'
            // de l'InvoiceItem devrait idéalement gérer la différence de stock.
            // Pour simplifier ici, on ne gère que les transitions de statut de la facture principale.
        }
    }
    */

    /*
    protected function deductStockAndCreateMovement(InvoiceItem $item, Invoice $invoice): void
    {
        $product = $item->product;
        $product->decrement('stock_quantity', $item->quantity);
        $newStockQuantity = $product->fresh()->stock_quantity;

        StockMovement::create([
            'product_id' => $item->product_id,
            'type' => 'sale',
            'quantity_changed' => -$item->quantity, // Négatif pour une sortie
            'new_stock_quantity_after_movement' => $newStockQuantity,
            'related_document_type' => Invoice::class,
            'related_document_id' => $invoice->id,
            'user_id' => $invoice->user_id, // Utilisateur de la facture
            'movement_date' => $invoice->invoice_date, // Date de la facture
            'notes' => "Vente - Facture: {$invoice->invoice_number}",
        ]);
        Log::info("[InvoiceObserver] StockMovement 'sale' created for Product ID: {$product->id}, InvoiceItem ID: {$item->id}");
    }
    */

    /*
    protected function reIncrementStockAndCreateMovement(InvoiceItem $item, Invoice $invoice): void
    {
        $product = $item->product;
        $product->increment('stock_quantity', $item->quantity);
        $newStockQuantity = $product->fresh()->stock_quantity;

        StockMovement::create([
            'product_id' => $item->product_id,
            'type' => 'sale_cancellation', // Ou 'voided_sale', 'returned_stock'
            'quantity_changed' => $item->quantity, // Positif pour une réintégration
            'new_stock_quantity_after_movement' => $newStockQuantity,
            'related_document_type' => Invoice::class,
            'related_document_id' => $invoice->id,
            'user_id' => auth()->check() && auth()->user() instanceof TenantUser ? auth()->user()->getKey() : $invoice->user_id,
            'movement_date' => now(), // Date de l'annulation/modification
            'reason' => "Annulation/Modification Facture: {$invoice->invoice_number}, Statut: {$invoice->status}",
        ]);
         Log::info("[InvoiceObserver] StockMovement 'sale_cancellation' created for Product ID: {$product->id}, InvoiceItem ID: {$item->id}");
    }
    */


    /**
     * Handle the Invoice "deleting" event.
     * Important si une facture est supprimée et que son stock doit être réintégré.
     */
    public function deleting(Invoice $invoice): void
    {
        Log::info("[InvoiceObserver] DELETING event for Invoice ID: {$invoice->id}, Status: {$invoice->status}");
        // Si la facture était liée à des livraisons qui ont sorti du stock,
        // la suppression de la facture ne devrait pas automatiquement réintégrer le stock.
        // La réintégration devrait se faire via l'annulation/suppression du Bon de Livraison.
        // Donc, on supprime aussi la logique de stock ici.
        
        // Ancien code commenté:
        /*
        $stockDeductingStatuses = ['sent', 'paid', 'overdue', 'confirmed'];
        if (in_array($invoice->status, $stockDeductingStatuses)) {
            Log::info("[InvoiceObserver] Re-incrementing stock due to invoice deletion.");
            foreach ($invoice->items()->with('product')->get() as $item) {
                if ($item->product) {
                     $this->reIncrementStockAndCreateMovement($item, $invoice);
                }
            }
        }
        */
    }
}