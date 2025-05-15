<?php

namespace App\Filament\Company\Resources\StockMovementResource\Pages;

use App\Filament\Company\Resources\StockMovementResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewStockMovement extends ViewRecord
{
    protected static string $resource = StockMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
