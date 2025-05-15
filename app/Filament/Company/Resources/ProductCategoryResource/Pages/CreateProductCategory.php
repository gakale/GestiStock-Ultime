<?php

namespace App\Filament\Company\Resources\ProductCategoryResource\Pages;

use App\Filament\Company\Resources\ProductCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateProductCategory extends CreateRecord
{
    protected static string $resource = ProductCategoryResource::class;
}
