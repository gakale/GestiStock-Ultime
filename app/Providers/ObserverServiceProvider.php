<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Location;
use App\Models\ProductLocationStock;
use App\Models\Warehouse;
use App\Observers\LocationObserver;
use App\Observers\ProductLocationStockObserver;
use App\Observers\WarehouseObserver;
use Illuminate\Support\ServiceProvider;

class ObserverServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Enregistrement des observateurs pour les modèles de gestion des emplacements
        Warehouse::observe(WarehouseObserver::class);
        Location::observe(LocationObserver::class);
        ProductLocationStock::observe(ProductLocationStockObserver::class);
    }
}
