<?php

namespace App\Filament\Company\Resources\PaymentReceivedResource\Pages;

use App\Filament\Company\Resources\PaymentReceivedResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPaymentReceiveds extends ListRecords
{
    protected static string $resource = PaymentReceivedResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
