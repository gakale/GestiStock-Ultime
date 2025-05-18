<?php

namespace App\Filament\Company\Resources\SupplierInvoiceResource\Pages;

use App\Filament\Company\Resources\SupplierInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSupplierInvoice extends ViewRecord
{
    protected static string $resource = SupplierInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->visible(fn($record) => $record->status !== 'paid' && $record->status !== 'cancelled'),
            Actions\DeleteAction::make()->visible(fn($record) => $record->status !== 'paid'),
        ];
    }

    public function getRelationManagers(): array
    {
        return [
            \App\Filament\Company\Resources\SupplierInvoiceResource\RelationManagers\ItemsRelationManager::class,
            \App\Filament\Company\Resources\SupplierInvoiceResource\RelationManagers\SupplierPaymentsRelationManager::class,
        ];
    }
}
