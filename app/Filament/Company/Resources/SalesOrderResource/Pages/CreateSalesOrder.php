<?php

namespace App\Filament\Company\Resources\SalesOrderResource\Pages;

use App\Filament\Company\Resources\SalesOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateSalesOrder extends CreateRecord
{
    protected static string $resource = SalesOrderResource::class;
}
