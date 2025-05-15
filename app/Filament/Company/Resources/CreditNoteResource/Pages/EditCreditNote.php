<?php

namespace App\Filament\Company\Resources\CreditNoteResource\Pages;

use App\Filament\Company\Resources\CreditNoteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCreditNote extends EditRecord
{
    protected static string $resource = CreditNoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn ($record) => $record->status === 'draft'),
            Actions\Action::make('issue')
                ->label('Émettre')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn ($record) => $record->status === 'draft')
                ->action(function () {
                    $this->record->status = 'issued';
                    $this->record->save();
                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                }),
        ];
    }
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
