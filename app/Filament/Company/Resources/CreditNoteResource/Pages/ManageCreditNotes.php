<?php

namespace App\Filament\Company\Resources\CreditNoteResource\Pages;

use App\Filament\Company\Resources\CreditNoteResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageCreditNotes extends ManageRecords
{
    protected static string $resource = CreditNoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
