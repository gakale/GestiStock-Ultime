<?php

namespace App\Filament\Company\Resources\DeliveryNoteResource\Pages;

use App\Filament\Company\Resources\DeliveryNoteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDeliveryNote extends EditRecord
{
    protected static string $resource = DeliveryNoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
