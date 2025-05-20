<?php

namespace App\Filament\Company\Resources\CreditNoteResource\Pages;

use App\Filament\Company\Resources\CreditNoteResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCreditNote extends CreateRecord
{
    protected static string $resource = CreditNoteResource::class;
    
    protected function afterCreate(): void
    {
        // Calculer les totaux après la création de l'avoir
        if ($this->record) {
            $this->record->calculateTotals();
        }
    }
    
    protected function getRedirectUrl(): string
    {
        // Rediriger vers la page de visualisation de l'avoir plutôt que l'index
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
