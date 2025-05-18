<?php

namespace App\Filament\Company\Resources\SupplierPaymentResource\Pages;

use App\Filament\Company\Resources\SupplierPaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSupplierPayment extends ViewRecord
{
    protected static string $resource = SupplierPaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
