<?php

namespace App\Observers;

use App\Models\SalesOrder;
use Illuminate\Support\Facades\Log;

class SalesOrderObserver
{
    // Peut être appelé après la sauvegarde d'un SalesOrderItem, DeliveryNoteItem, InvoiceItem
    // Pour l'instant, on le met dans l'événement 'updated' du SalesOrder lui-même,
    // en supposant que les mises à jour des items déclenchent un 'touch' sur le SalesOrder
    // ou que la méthode checkAndUpdateStatus est appelée explicitement.

    /**
     * Handle the SalesOrder "updated" event.
     */
    public function updated(SalesOrder $salesOrder): void
    {
        // $this->checkAndUpdateStatus($salesOrder); // Déplacer la logique ici si appelée par 'touch'
    }
     /**
     * Handle the SalesOrder "saved" event (created or updated).
     */
    public function saved(SalesOrder $salesOrder): void
    {
        // Appeler explicitement après une modification d'item
        // ou si on veut recalculer le statut à chaque sauvegarde du SO
        // $this->checkAndUpdateStatus($salesOrder);
    }


    // Cette méthode pourrait aussi être sur le modèle SalesOrder
    public function checkAndUpdateStatus(SalesOrder $salesOrder): void
    {
        // Ne pas changer le statut si la commande est déjà 'completed' ou 'cancelled'
        if (in_array($salesOrder->status, ['completed', 'cancelled'])) {
            return;
        }

        $salesOrder->loadMissing('items'); // S'assurer que les items sont chargés

        $totalOrdered = $salesOrder->items->sum('quantity_ordered');
        $totalShipped = $salesOrder->items->sum('quantity_shipped');
        $totalInvoiced = $salesOrder->items->sum('quantity_invoiced');

        $isFullyShipped = $totalShipped >= $totalOrdered;
        $isPartiallyShipped = $totalShipped > 0 && $totalShipped < $totalOrdered;

        $isFullyInvoiced = $totalInvoiced >= $totalOrdered;
        $isPartiallyInvoiced = $totalInvoiced > 0 && $totalInvoiced < $totalOrdered;

        $newStatus = $salesOrder->status; // Garder le statut actuel par défaut

        if ($isFullyShipped && $isFullyInvoiced) {
            $newStatus = 'completed';
        } elseif ($isFullyShipped) {
            $newStatus = 'fully_shipped'; // Peut encore être partiellement facturé
            if($isPartiallyInvoiced) $newStatus = 'partially_invoiced'; // Préciser
        } elseif ($isPartiallyShipped) {
            $newStatus = 'partially_shipped';
             if($isPartiallyInvoiced) $newStatus = 'partially_invoiced'; // Préciser
        } elseif ($isFullyInvoiced && !$isFullyShipped && $salesOrder->status !== 'confirmed') {
             // Si tout est facturé mais pas encore (totalement) livré,
             // on ne change pas le statut vers fully_invoiced que si la commande est au moins confirmée
        } else if ($isPartiallyInvoiced && $salesOrder->status !== 'confirmed' ) {

        }


        // Priorité au statut d'expédition pour l'affichage général
        if ($salesOrder->status === 'confirmed') { // Ne changer que si la commande est confirmée
            if ($isFullyShipped && $isFullyInvoiced) {
                $newStatus = 'completed';
            } elseif ($isFullyShipped) {
                $newStatus = 'fully_shipped';
            } elseif ($isPartiallyShipped) {
                $newStatus = 'partially_shipped';
            }
            // La facturation peut être un état superposé
            if ($isFullyInvoiced && $newStatus !== 'completed') {
                // On pourrait avoir un statut combiné ou une logique de badges multiples
            } elseif ($isPartiallyInvoiced && $newStatus !== 'completed' && !str_contains($newStatus, 'shipped')) {
                 // newStatus = 'partially_invoiced'; // Attention à ne pas écraser un statut d'expédition plus avancé
            }
        }


        if ($newStatus !== $salesOrder->status && $salesOrder->status !== 'pending_confirmation') {
            Log::info("[SalesOrderUpdater] Updating SalesOrder {$salesOrder->order_number} status from {$salesOrder->status} to {$newStatus}");
            $salesOrder->update(['status' => $newStatus]);
        }
    }
}