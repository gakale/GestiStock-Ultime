<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WarehouseObserver
{
    /**
     * Handle the Warehouse "creating" event.
     */
    public function creating(Warehouse $warehouse): void
    {
        // Définir explicitement tenant_id avant la création
        if (empty($warehouse->tenant_id)) {
            try {
                // Obtenir l'ID du tenant à partir de la connexion actuelle
                $currentDb = DB::connection()->getDatabaseName();
                if (str_starts_with($currentDb, 'tenant_')) {
                    $tenantId = str_replace('tenant_', '', $currentDb);
                    $warehouse->tenant_id = $tenantId;
                    Log::info("WarehouseObserver: tenant_id défini sur {$tenantId} pour l'entrepôt {$warehouse->name}");
                } else {
                    Log::warning("WarehouseObserver: Impossible de déterminer le tenant_id à partir de la base de données {$currentDb}");
                }
            } catch (\Exception $e) {
                Log::error("WarehouseObserver: Erreur lors de la définition du tenant_id: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Handle the Warehouse "created" event.
     */
    public function created(Warehouse $warehouse): void
    {
        // Vérifier si tenant_id est défini après la création
        if (empty($warehouse->tenant_id)) {
            Log::warning("WarehouseObserver: tenant_id non défini après la création pour l'entrepôt {$warehouse->id}");
        }
    }

    /**
     * Handle the Warehouse "updated" event.
     */
    public function updated(Warehouse $warehouse): void
    {
        //
    }

    /**
     * Handle the Warehouse "deleted" event.
     */
    public function deleted(Warehouse $warehouse): void
    {
        //
    }

    /**
     * Handle the Warehouse "restored" event.
     */
    public function restored(Warehouse $warehouse): void
    {
        //
    }

    /**
     * Handle the Warehouse "force deleted" event.
     */
    public function forceDeleted(Warehouse $warehouse): void
    {
        //
    }
}
