<?php

declare(strict_types=1);

namespace App\Filament\Company\Resources\LocationResource\Pages;

use App\Filament\Company\Resources\LocationResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateLocation extends CreateRecord
{
    protected static string $resource = LocationResource::class;
    
    /**
     * Vérifie que les données incluent bien tenant_id 
     * avant que le modèle ne soit créé dans la base de données
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // S'assurer que tenant_id est défini avec la valeur du tenant courant
        if (empty($data['tenant_id'])) {
            // Première méthode: utiliser la fonction tenant()
            if (function_exists('tenant') && tenant()) {
                $data['tenant_id'] = tenant()->getTenantKey();
                \Log::info("CreateLocation: tenant_id défini via tenant(): {$data['tenant_id']}");
            }
            // Deuxième méthode: extraire du nom de la base de données
            else {
                try {
                    $currentDb = DB::connection()->getDatabaseName();
                    if (str_starts_with($currentDb, 'tenant_')) {
                        $data['tenant_id'] = str_replace('tenant_', '', $currentDb);
                        \Log::info("CreateLocation: tenant_id défini depuis le nom de la base de données: {$data['tenant_id']}");
                    }
                } catch (\Exception $e) {
                    \Log::error("CreateLocation: Impossible de déterminer le tenant_id: " . $e->getMessage());
                }
            }
            
            // Vérification finale
            if (empty($data['tenant_id'])) {
                \Log::error("CreateLocation: Impossible de déterminer le tenant_id pour un nouvel emplacement");
                // Dans un environnement de production, on pourrait lever une exception ici
                // throw new \Exception("Impossible de déterminer le tenant_id pour l'emplacement.");
            }
        }
        
        return $data;
    }
    
    /**
     * Rediriger vers la liste des emplacements après la création
     */
    /**
     * Définir le tenant_id directement sur le modèle avant sa sauvegarde
     */
    /**
     * Forcer la présence de tenant_id juste avant la création du modèle
     */
    protected function handleRecordCreation(array $data): Model
    {
        if (empty($data['tenant_id'])) {
            if (function_exists('tenant') && tenant()) {
                $data['tenant_id'] = tenant()->getTenantKey();
            } else {
                try {
                    $currentDb = DB::connection()->getDatabaseName();
                    if (str_starts_with($currentDb, 'tenant_')) {
                        $data['tenant_id'] = str_replace('tenant_', '', $currentDb);
                    }
                } catch (\Exception $e) {}
            }
        }
        return static::getModel()::create($data);
    }

    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
