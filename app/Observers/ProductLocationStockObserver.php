<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\ProductLocationStock;
use App\Models\Product;
use App\Models\Location;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\App;

class ProductLocationStockObserver
{
    /**
     * Handle the ProductLocationStock "creating" event.
     */
    public function creating(ProductLocationStock $productLocationStock): void
    {
        // Définir explicitement tenant_id avant la création
        if (empty($productLocationStock->tenant_id)) {
            try {
                // Méthode 1: Utiliser la fonction tenant() si disponible
                if (function_exists('tenant') && tenant()) {
                    $productLocationStock->tenant_id = tenant()->getTenantKey();
                    Log::info("ProductLocationStockObserver: tenant_id défini via tenant() pour le stock {$productLocationStock->id}");
                }
                // Méthode 2: Obtenir le tenant_id à partir du produit associé
                elseif (!empty($productLocationStock->product_id)) {
                    $product = Product::find($productLocationStock->product_id);
                    if ($product && !empty($product->tenant_id)) {
                        $productLocationStock->tenant_id = $product->tenant_id;
                        Log::info("ProductLocationStockObserver: tenant_id défini via le produit associé pour le stock {$productLocationStock->id}");
                    }
                }
                // Méthode 3: Obtenir le tenant_id à partir de l'emplacement associé
                elseif (!empty($productLocationStock->location_id)) {
                    $location = Location::find($productLocationStock->location_id);
                    if ($location && !empty($location->tenant_id)) {
                        $productLocationStock->tenant_id = $location->tenant_id;
                        Log::info("ProductLocationStockObserver: tenant_id défini via l'emplacement associé pour le stock {$productLocationStock->id}");
                    }
                }
                // Méthode 4: Obtenir l'ID du tenant à partir de la connexion actuelle
                else {
                    $currentDb = DB::connection()->getDatabaseName();
                    if (str_starts_with($currentDb, 'tenant_')) {
                        $tenantId = str_replace('tenant_', '', $currentDb);
                        $productLocationStock->tenant_id = $tenantId;
                        Log::info("ProductLocationStockObserver: tenant_id défini via la base de données {$currentDb} pour le stock {$productLocationStock->id}");
                    } else {
                        Log::warning("ProductLocationStockObserver: Impossible de déterminer le tenant_id à partir de la base de données {$currentDb}");
                    }
                }
            } catch (\Exception $e) {
                Log::error("ProductLocationStockObserver: Erreur lors de la définition du tenant_id: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Handle the ProductLocationStock "created" event.
     */
    public function created(ProductLocationStock $productLocationStock): void
    {
        // Vérifier si tenant_id est défini après la création
        if (empty($productLocationStock->tenant_id)) {
            Log::warning("ProductLocationStockObserver: tenant_id non défini après la création pour le stock {$productLocationStock->id}");
        }
    }

    /**
     * Handle the ProductLocationStock "updated" event.
     */
    public function updated(ProductLocationStock $productLocationStock): void
    {
        //
    }

    /**
     * Handle the ProductLocationStock "deleted" event.
     */
    public function deleted(ProductLocationStock $productLocationStock): void
    {
        //
    }

    /**
     * Handle the ProductLocationStock "restored" event.
     */
    public function restored(ProductLocationStock $productLocationStock): void
    {
        //
    }

    /**
     * Handle the ProductLocationStock "force deleted" event.
     */
    public function forceDeleted(ProductLocationStock $productLocationStock): void
    {
        //
    }
}
