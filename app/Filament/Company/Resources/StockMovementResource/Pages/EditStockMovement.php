<?php

namespace App\Filament\Company\Resources\StockMovementResource\Pages;

use App\Filament\Company\Resources\StockMovementResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStockMovement extends EditRecord
{
    protected static string $resource = StockMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
