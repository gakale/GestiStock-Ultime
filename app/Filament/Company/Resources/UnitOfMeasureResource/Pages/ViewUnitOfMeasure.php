<?php

namespace App\Filament\Company\Resources\UnitOfMeasureResource\Pages;

use App\Filament\Company\Resources\UnitOfMeasureResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewUnitOfMeasure extends ViewRecord
{
    protected static string $resource = UnitOfMeasureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
