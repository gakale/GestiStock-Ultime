<?php

namespace App\Filament\Company\Resources\PurchaseOrderResource\Pages;

use App\Filament\Company\Resources\PurchaseOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchaseOrder extends CreateRecord
{
    protected static string $resource = PurchaseOrderResource::class;
    
    /**
     * Modifier les données du formulaire avant la création
     *
     * @param array $data
     * @return array
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (!isset($data['user_id']) && auth()->check()) {
            $user = auth()->user(); // Ceci sera une instance de TenantUser
            if ($user instanceof \App\Models\TenantUser) { // Bonne pratique d'ajouter la vérification de type
                $data['user_id'] = (string) $user->getKey(); // getKey() retournera l'UUID
            } else {
                // Gérer le cas où l'utilisateur authentifié n'est pas un TenantUser
                // Peut-être logger une erreur ou assigner null si la colonne user_id est nullable
                // Pour l'instant, on suppose que c'est toujours un TenantUser dans ce contexte
                $data['user_id'] = null;
            }
        }
        return $data;
    }
}
