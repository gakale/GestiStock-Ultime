<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\CreditNote;
use Illuminate\Support\Facades\Log;
use App\Observers\CreditNoteItemObserver;

class CreditNoteObserver
{
    /**
     * Handle the CreditNote "created" event.
     */
    public function created(CreditNote $creditNote): void
    {
        Log::info("[CreditNoteObserver] CreditNote created: {$creditNote->credit_note_number}");
        
        // Calculer les totaux si des articles sont déjà associés
        if ($creditNote->items()->count() > 0) {
            $creditNote->calculateTotals();
        }
    }

    /**
     * Handle the CreditNote "updated" event.
     */
    public function updated(CreditNote $creditNote): void
    {
        Log::info("[CreditNoteObserver] CreditNote updated: {$creditNote->credit_note_number}");

        $originalStatus = $creditNote->getOriginal('status');
        $newStatus = $creditNote->status;

        if ($creditNote->wasChanged('status')) {
            Log::info("[CreditNoteObserver] Status changed for CN #{$creditNote->credit_note_number}: {$originalStatus} -> {$newStatus}");

            $stockImpactingStatuses = ['issued', 'applied'];
            $wasStockImpacting = in_array($originalStatus, $stockImpactingStatuses);
            $isNowStockImpacting = in_array($newStatus, $stockImpactingStatuses);

            // Récupérer l'instance de l'observateur d'item via le conteneur de services
            $itemObserver = app(CreditNoteItemObserver::class);

            if (!$wasStockImpacting && $isNowStockImpacting) {
                Log::info("[CreditNoteObserver] CN #{$creditNote->credit_note_number} status to stock impacting. Processing items for stock increase.");
                // Le drapeau restock_items doit aussi être vérifié ici ou dans l'item observer
                if ($creditNote->restock_items) {
                    foreach ($creditNote->items()->with(['product', 'transactionUnit', 'product.stockUnit'])->get() as $item) {
                        // Simuler un événement 'create' pour le stock car il n'a pas encore été impacté
                        $itemObserver->processStockUpdate($item, 'create_due_to_cn_status_change');
                    }
                } else {
                    Log::info("[CreditNoteObserver] CN #{$creditNote->credit_note_number} not marked for restock. No stock changes processed despite status change.");
                }
            } elseif ($wasStockImpacting && !$isNowStockImpacting) {
                Log::info("[CreditNoteObserver] CN #{$creditNote->credit_note_number} status from stock impacting. Processing items for stock decrease/cancellation.");
                // Le drapeau restock_items doit être cohérent avec l'action initiale.
                // Si les items ont été remis en stock, leur retour doit être annulé.
                // On suppose que si restock_items était true initialement, on annule.
                // Si restock_items est false maintenant, mais était true avant, il faut quand même annuler l'action précédente.
                // La logique actuelle de processStockUpdate dans CreditNoteItemObserver ne vérifie pas restock_items explicitement
                // mais le statut de la note de crédit. Si le statut n'est plus impactant, processStockUpdate ne fera rien.
                // Donc, ici, nous déclenchons une 'annulation' si le statut précédent était impactant.
                foreach ($creditNote->items()->with(['product', 'transactionUnit', 'product.stockUnit'])->get() as $item) {
                    // Simuler un événement 'delete' pour le stock pour annuler l'impact précédent
                    $itemObserver->processStockUpdate($item, 'delete_due_to_cn_status_change');
                }
            }
        }

        // Recalculer les totaux si le statut ou le drapeau de restockage a changé, ou si des items ont potentiellement forcé un recalcul.
        // La méthode calculateTotalsAndSave est supposée exister et sauvegarder.
        if ($creditNote->wasChanged(['status', 'restock_items']) || $creditNote->isDirty() || (isset($isNowStockImpacting) && $isNowStockImpacting) || (isset($wasStockImpacting) && !$isNowStockImpacting && $wasStockImpacting) ) {
            Log::info("[CreditNoteObserver] Recalculating totals for CreditNote #{$creditNote->credit_note_number}");
            if (method_exists($creditNote, 'calculateTotalsAndSave')) {
                $creditNote->calculateTotalsAndSave(); 
            } elseif (method_exists($creditNote, 'calculateTotals')) {
                $creditNote->calculateTotals(); // Supposant qu'elle sauvegarde, ou que save() sera appelé plus tard
                 // $creditNote->saveQuietly(); // Si calculateTotals ne sauvegarde pas
            } else {
                Log::warning("[CreditNoteObserver] Method calculateTotalsAndSave or calculateTotals not found on CreditNote model.");
            }
        }
    }

    /**
     * Handle the CreditNote "deleted" event.
     */
    public function deleted(CreditNote $creditNote): void
    {
        Log::info("[CreditNoteObserver] CreditNote deleted: {$creditNote->credit_note_number}");
        // Pas d'action spécifique nécessaire ici, les articles seront supprimés par cascade
    }

    /**
     * Handle the CreditNote "restored" event.
     */
    public function restored(CreditNote $creditNote): void
    {
        Log::info("[CreditNoteObserver] CreditNote restored: {$creditNote->credit_note_number}");
        $creditNote->calculateTotals();
    }

    /**
     * Handle the CreditNote "force deleted" event.
     */
    public function forceDeleted(CreditNote $creditNote): void
    {
        Log::info("[CreditNoteObserver] CreditNote force deleted: {$creditNote->credit_note_number}");
        // Pas d'action spécifique nécessaire ici
    }
}
