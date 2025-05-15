<?php

namespace App\Filament\Company\Resources\PurchaseOrderResource\Pages;

use App\Filament\Company\Resources\PurchaseOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManagePurchaseOrders extends ManageRecords
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
