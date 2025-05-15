<?php

namespace App\Filament\Company\Resources\PaymentReceivedResource\Pages;

use App\Filament\Company\Resources\PaymentReceivedResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPaymentReceived extends ViewRecord
{
    protected static string $resource = PaymentReceivedResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
