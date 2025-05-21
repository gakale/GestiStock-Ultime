<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Location;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LocationObserver
{
    /**
     * Handle the Location "creating" event.
     */
    public function creating(Location $location): void
    {
        // Définir explicitement tenant_id avant la création
        if (empty($location->tenant_id)) {
            try {
                // Obtenir l'ID du tenant à partir de la connexion actuelle
                $currentDb = DB::connection()->getDatabaseName();
                if (str_starts_with($currentDb, 'tenant_')) {
                    $tenantId = str_replace('tenant_', '', $currentDb);
                    $location->tenant_id = $tenantId;
                    Log::info("LocationObserver: tenant_id défini sur {$tenantId} pour l'emplacement {$location->name}");
                } else {
                    Log::warning("LocationObserver: Impossible de déterminer le tenant_id à partir de la base de données {$currentDb}");
                }
            } catch (\Exception $e) {
                Log::error("LocationObserver: Erreur lors de la définition du tenant_id: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Handle the Location "created" event.
     */
    public function created(Location $location): void
    {
        // Vérifier si tenant_id est défini après la création
        if (empty($location->tenant_id)) {
            Log::warning("LocationObserver: tenant_id non défini après la création pour l'emplacement {$location->id}");
        }
    }

    /**
     * Handle the Location "updated" event.
     */
    public function updated(Location $location): void
    {
        //
    }

    /**
     * Handle the Location "deleted" event.
     */
    public function deleted(Location $location): void
    {
        //
    }

    /**
     * Handle the Location "restored" event.
     */
    public function restored(Location $location): void
    {
        //
    }

    /**
     * Handle the Location "force deleted" event.
     */
    public function forceDeleted(Location $location): void
    {
        //
    }
}
