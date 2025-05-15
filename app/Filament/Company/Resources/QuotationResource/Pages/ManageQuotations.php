<?php

namespace App\Filament\Company\Resources\QuotationResource\Pages;

use App\Filament\Company\Resources\QuotationResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageQuotations extends ManageRecords
{
    protected static string $resource = QuotationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
