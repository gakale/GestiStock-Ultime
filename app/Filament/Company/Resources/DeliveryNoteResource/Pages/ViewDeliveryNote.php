<?php

namespace App\Filament\Company\Resources\DeliveryNoteResource\Pages;

use App\Filament\Company\Resources\DeliveryNoteResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewDeliveryNote extends ViewRecord
{
    protected static string $resource = DeliveryNoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
