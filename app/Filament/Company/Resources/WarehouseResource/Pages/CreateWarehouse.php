<?php

namespace App\Filament\Company\Resources\WarehouseResource\Pages;

use App\Filament\Company\Resources\WarehouseResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateWarehouse extends CreateRecord
{
    protected static string $resource = WarehouseResource::class;
    
    /**
     * Vérifie que les données incluent bien tenant_id 
     * avant que le modèle ne soit créé dans la base de données
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // S'assurer que tenant_id est défini avec la valeur du tenant courant
        if (function_exists('tenant') && tenant()) {
            $data['tenant_id'] = tenant()->getTenantKey();
        } else {
            // Essayer de récupérer l'ID du tenant depuis la connexion de base de données
            try {
                $currentDb = \DB::connection()->getDatabaseName();
                if (str_starts_with($currentDb, 'tenant_')) {
                    $data['tenant_id'] = str_replace('tenant_', '', $currentDb);
                }
            } catch (\Exception $e) {
                // Log l'erreur mais continue le processus
                \Log::error("Impossible de déterminer le tenant_id: " . $e->getMessage());
            }
        }
        
        return $data;
    }
    
    /**
     * Rediriger vers la liste des entrepôts après la création
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}