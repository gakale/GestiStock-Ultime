<?php

namespace App\Filament\Company\Resources\SupplierInvoiceResource\Pages;

use App\Filament\Company\Resources\SupplierInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSupplierInvoice extends EditRecord
{
    protected static string $resource = SupplierInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
