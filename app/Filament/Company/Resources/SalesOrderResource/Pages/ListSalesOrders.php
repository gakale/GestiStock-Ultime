<?php

namespace App\Filament\Company\Resources\SalesOrderResource\Pages;

use App\Filament\Company\Resources\SalesOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSalesOrders extends ListRecords
{
    protected static string $resource = SalesOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
