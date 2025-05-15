<?php

namespace App\Observers;

use App\Models\DeliveryNoteItem;
use Illuminate\Support\Facades\Log;

class DeliveryNoteItemObserver
{
    public function created(DeliveryNoteItem $deliveryNoteItem): void
    {
        Log::info("[DeliveryNoteItemObserver] CREATED event for DeliveryNoteItem ID: {$deliveryNoteItem->id}. Parent DeliveryNote status: " . $deliveryNoteItem->deliveryNote?->status);
        // Plus de logique de stock ici, sera gérée par DeliveryNoteObserver
    }

    public function updated(DeliveryNoteItem $deliveryNoteItem): void
    {
        Log::info("[DeliveryNoteItemObserver] UPDATED event for DeliveryNoteItem ID: {$deliveryNoteItem->id}.");
        // Si la quantité d'un item change sur un BL déjà "shipped",
        // DeliveryNoteObserver ne le verra pas directement.
        // Il faudrait une logique ici pour ajuster le stock si le BL parent est "shipped" ou "delivered".
        // Ou, une action utilisateur dédiée "Ajuster livraison" qui modifie le BL et ses items,
        // puis re-déclenche la logique dans DeliveryNoteObserver ou un service.
        // Pour l'instant, on garde simple.
    }


    public function deleted(DeliveryNoteItem $deliveryNoteItem): void
    {
        Log::info("[DeliveryNoteItemObserver] DELETED event for DeliveryNoteItem ID: {$deliveryNoteItem->id}.");
        // Plus de logique de stock ici
    }
}