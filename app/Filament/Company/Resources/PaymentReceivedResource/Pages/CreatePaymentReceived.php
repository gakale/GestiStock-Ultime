<?php

namespace App\Filament\Company\Resources\PaymentReceivedResource\Pages;

use App\Filament\Company\Resources\PaymentReceivedResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePaymentReceived extends CreateRecord
{
    protected static string $resource = PaymentReceivedResource::class;
}
