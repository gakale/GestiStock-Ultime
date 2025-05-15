<?php

namespace App\Filament\Company\Resources\PaymentReceivedResource\Pages;

use App\Filament\Company\Resources\PaymentReceivedResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPaymentReceived extends EditRecord
{
    protected static string $resource = PaymentReceivedResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
