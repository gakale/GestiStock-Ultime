<?php

declare(strict_types=1);

namespace App\Filament\Company\Resources\LocationResource\Pages;

use App\Filament\Company\Resources\LocationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditLocation extends EditRecord
{
    protected static string $resource = LocationResource::class;

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
        if (!isset($data['tenant_id']) || empty($data['tenant_id'])) {
            // Première méthode: utiliser le tenant_id de l'enregistrement existant (le plus fiable)
            if ($this->record && !empty($this->record->tenant_id)) {
                $data['tenant_id'] = $this->record->tenant_id;
                \Log::info("EditLocation: tenant_id défini depuis l'enregistrement existant: {$data['tenant_id']}");
            }
            // Deuxième méthode: utiliser la fonction tenant()
            elseif (function_exists('tenant') && tenant()) {
                $data['tenant_id'] = tenant()->getTenantKey();
                \Log::info("EditLocation: tenant_id défini via tenant(): {$data['tenant_id']}");
            }
            // Troisième méthode: extraire du nom de la base de données
            else {
                try {
                    $currentDb = DB::connection()->getDatabaseName();
                    if (str_starts_with($currentDb, 'tenant_')) {
                        $data['tenant_id'] = str_replace('tenant_', '', $currentDb);
                        \Log::info("EditLocation: tenant_id défini depuis le nom de la base de données: {$data['tenant_id']}");
                    }
                } catch (\Exception $e) {
                    \Log::error("EditLocation: Impossible de déterminer le tenant_id: " . $e->getMessage());
                }
            }
            
            // Vérification finale
            if (empty($data['tenant_id'])) {
                \Log::error("EditLocation: Impossible de déterminer le tenant_id pour l'emplacement ID: {$this->record->id}");
                // Dans un environnement de production, on pourrait lever une exception ici
                // throw new \Exception("Impossible de déterminer le tenant_id pour l'emplacement.");
            }
        }
        
        return $data;
    }
}
