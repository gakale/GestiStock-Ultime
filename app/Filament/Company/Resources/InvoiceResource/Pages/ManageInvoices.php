<?php

namespace App\Filament\Company\Resources\InvoiceResource\Pages;

use App\Filament\Company\Resources\InvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageInvoices extends ManageRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
