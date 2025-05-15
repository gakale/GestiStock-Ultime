<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use App\Models\Tenant; // Votre modèle Tenant

class DebugTenantRoutes extends Command
{
    protected $signature = 'debug:tenant-routes {tenantId}';
    protected $description = 'List routes for a specific tenant context';

    public function handle()
    {
        $tenantId = $this->argument('tenantId');
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            $this->error("Tenant with ID {$tenantId} not found.");
            return 1;
        }

        tenancy()->initialize($tenant);

        $this->info("Routes for tenant: {$tenant->name} (ID: {$tenant->id}, Domain: " . $tenant->domains->first()?->domain . ")");
        $this->line("----------------------------------------------------");

        // Vider le cache de route en mémoire pour ce contexte (peut ne pas être nécessaire mais ne fait pas de mal)
        app()->booted(function () {
            app('router')->getRoutes()->refreshNameLookups();
            app('router')->getRoutes()->refreshActionLookups();
        });


        Artisan::call('route:list', [
            '--path' => 'company-admin', // Filtre pour voir spécifiquement les routes du panel
            // '--name' => 'filament.company.*' // Si vous connaissez le préfixe des noms de route
        ]);

        $this->line(Artisan::output());

        tenancy()->end(); // Important de terminer le contexte du tenant
        return 0;
    }
}