<?php

namespace App\Filament\Company\Resources\WarehouseResource\Pages;

use App\Filament\Company\Resources\WarehouseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWarehouse extends EditRecord
{
    protected static string $resource = WarehouseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
    
    /**
     * Vérifie que les données incluent tenant_id avant la mise à jour
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Maintenir le tenant_id actuel en cas de modification
        // Note : ceci est généralement géré automatiquement par le trait BelongsToTenant
        // mais nous l'ajoutons par sécurité
        if (!isset($data['tenant_id'])) {
            // Vérifier si tenant() existe et n'est pas null
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
                    \Log::error("Impossible de déterminer le tenant_id lors de la modification d'un entrepôt: " . $e->getMessage());
                }
            }
            
            // Si nous n'avons toujours pas de tenant_id, utiliser celui de l'enregistrement existant
            if (empty($data['tenant_id']) && $this->record) {
                $data['tenant_id'] = $this->record->tenant_id;
            }
        }
        
        return $data;
    }
}