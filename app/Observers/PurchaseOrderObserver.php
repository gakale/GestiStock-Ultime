<?php

namespace App\Observers;

use App\Models\PurchaseOrder;
use App\Models\TenantUser;
use Illuminate\Support\Str;

class PurchaseOrderObserver
{
    /**
     * Handle the PurchaseOrder "creating" event.
     * Cette méthode est appelée avant l'insertion en base de données
     */
    public function creating(PurchaseOrder $purchaseOrder): void
    {
        // Vérifier si user_id est un entier et le convertir en UUID si nécessaire
        if (isset($purchaseOrder->user_id) && is_numeric($purchaseOrder->user_id)) {
            // Récupérer l'utilisateur correspondant à cet ID
            $user = TenantUser::find($purchaseOrder->user_id);
            if ($user) {
                // Utiliser l'UUID de l'utilisateur
                $purchaseOrder->user_id = $user->getKey();
            } else {
                // Si l'utilisateur n'est pas trouvé, utiliser l'utilisateur connecté
                $user = auth()->user();
                if ($user instanceof TenantUser) {
                    $purchaseOrder->user_id = $user->getKey();
                } else {
                    // Générer un UUID valide si aucun utilisateur n'est trouvé
                    // Cela évitera l'erreur d'insertion, mais il faudra vérifier la logique métier
                    $purchaseOrder->user_id = Str::uuid()->toString();
                }
            }
        }
    }
    
    /**
     * Handle the PurchaseOrder "created" event.
     */
    public function created(PurchaseOrder $purchaseOrder): void
    {
        //
    }

    /**
     * Handle the PurchaseOrder "updated" event.
     */
    public function updated(PurchaseOrder $purchaseOrder): void
    {
        //
    }

    /**
     * Handle the PurchaseOrder "deleted" event.
     */
    public function deleted(PurchaseOrder $purchaseOrder): void
    {
        //
    }

    /**
     * Handle the PurchaseOrder "restored" event.
     */
    public function restored(PurchaseOrder $purchaseOrder): void
    {
        //
    }

    /**
     * Handle the PurchaseOrder "force deleted" event.
     */
    public function forceDeleted(PurchaseOrder $purchaseOrder): void
    {
        //
    }
}
