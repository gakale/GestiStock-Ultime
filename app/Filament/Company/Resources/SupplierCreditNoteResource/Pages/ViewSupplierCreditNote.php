<?php

namespace App\Filament\Company\Resources\SupplierCreditNoteResource\Pages;

use App\Filament\Company\Resources\SupplierCreditNoteResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSupplierCreditNote extends ViewRecord
{
    protected static string $resource = SupplierCreditNoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
