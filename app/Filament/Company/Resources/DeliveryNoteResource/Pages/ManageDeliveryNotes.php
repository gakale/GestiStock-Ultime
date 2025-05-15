<?php

namespace App\Filament\Company\Resources\DeliveryNoteResource\Pages;

use App\Filament\Company\Resources\DeliveryNoteResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageDeliveryNotes extends ManageRecords
{
    protected static string $resource = DeliveryNoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
