<?php

declare(strict_types=1);

namespace App\Filament\Company\Resources\WarehouseResource\Pages;

use App\Filament\Company\Resources\WarehouseResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWarehouse extends ViewRecord
{
    protected static string $resource = WarehouseResource::class;
    
    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Retour')
                ->color('gray')
                ->icon('heroicon-o-arrow-left')
                ->url($this->getResource()::getUrl()),
            
            Action::make('edit')
                ->label('Modifier')
                ->color('warning')
                ->icon('heroicon-o-pencil')
                ->url($this->getResource()::getUrl('edit', ['record' => $this->getRecord()])),
            
            DeleteAction::make()
                ->label('Supprimer')
                ->color('danger')
                ->icon('heroicon-o-trash'),
        ];
    }
}
