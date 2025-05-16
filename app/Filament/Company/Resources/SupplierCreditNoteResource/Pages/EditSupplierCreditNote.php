<?php

namespace App\Filament\Company\Resources\SupplierCreditNoteResource\Pages;

use App\Filament\Company\Resources\SupplierCreditNoteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;

class EditSupplierCreditNote extends EditRecord
{
    protected static string $resource = SupplierCreditNoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\Action::make('confirm')
                ->label('Confirmer l\'avoir')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->visible(fn ($record) => $record->status === 'draft')
                ->action(function () {
                    $record = $this->getRecord();
                    $record->status = 'confirmed';
                    $record->save();
                    
                    Notification::make()
                        ->success()
                        ->title('L\'avoir a été confirmé avec succès.')
                        ->send();
                        
                    $this->redirect(static::getResource()::getUrl('view', ['record' => $record]));
                })
                ->requiresConfirmation()
                ->modalHeading('Confirmer l\'avoir fournisseur')
                ->modalDescription('Confirmer cet avoir déclenchera les mouvements de stock si l\'option "Les articles ont quitté notre stock pour le fournisseur" est activée. Voulez-vous continuer ?')
                ->modalSubmitActionLabel('Oui, confirmer l\'avoir'),
                
            Actions\Action::make('cancel')
                ->label('Annuler l\'avoir')
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->visible(fn ($record) => $record->status === 'confirmed')
                ->action(function () {
                    $record = $this->getRecord();
                    $record->status = 'cancelled';
                    $record->save();
                    
                    Notification::make()
                        ->success()
                        ->title('L\'avoir a été annulé avec succès.')
                        ->send();
                        
                    $this->redirect(static::getResource()::getUrl('view', ['record' => $record]));
                })
                ->requiresConfirmation()
                ->modalHeading('Annuler l\'avoir fournisseur')
                ->modalDescription('Annuler cet avoir inversera les mouvements de stock si l\'option "Les articles ont quitté notre stock pour le fournisseur" était activée. Voulez-vous continuer ?')
                ->modalSubmitActionLabel('Oui, annuler l\'avoir'),
                
            Actions\DeleteAction::make(),
        ];
    }
    
    // La méthode mutateFormDataBeforeFill n'est plus nécessaire car Filament gère automatiquement
    // le chargement des items via le Repeater avec ->relationship()
    // protected function mutateFormDataBeforeFill(array $data): array
    // {
    //     return $data;
    // }
    
    protected function afterSave(): void
    {
        // Recalculer les totaux après la sauvegarde
        $this->getRecord()->calculateTotals();
    }
}
