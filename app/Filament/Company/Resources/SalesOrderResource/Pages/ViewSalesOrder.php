<?php

namespace App\Filament\Company\Resources\SalesOrderResource\Pages;

use App\Filament\Company\Resources\SalesOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSalesOrder extends ViewRecord
{
    protected static string $resource = SalesOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
