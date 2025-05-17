<?php

namespace App\Filament\Company\Resources\InventorySessionResource\Pages;

use App\Filament\Company\Resources\InventorySessionResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateInventorySession extends CreateRecord
{
    protected static string $resource = InventorySessionResource::class;

    // Rediriger vers la page d'édition après la création pour ajouter les items
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Session d\'inventaire créée. Vous pouvez maintenant ajouter les produits à inventorier.';
    }
}
