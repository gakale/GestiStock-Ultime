<?php

namespace App\Filament\Company\Resources\GoodsReceiptResource\Pages;

use App\Filament\Company\Resources\GoodsReceiptResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageGoodsReceipts extends ManageRecords
{
    protected static string $resource = GoodsReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
