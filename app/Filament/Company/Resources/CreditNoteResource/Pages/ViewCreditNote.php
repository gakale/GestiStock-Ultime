<?php

namespace App\Filament\Company\Resources\CreditNoteResource\Pages;

use App\Filament\Company\Resources\CreditNoteResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCreditNote extends ViewRecord
{
    protected static string $resource = CreditNoteResource::class;
    
    // Activer le polling pour rafraîchir automatiquement les données toutes les 5 secondes
    protected function getPollingInterval(): ?string
    {
        return '5s';
    }
    
    public function mount(int|string $record): void
    {
        parent::mount($record);
        
        $this->refreshRecord();
    }
    
    // Méthode pour rafraîchir l'enregistrement et recalculer les totaux
    public function refreshRecord(): void
    {
        // Recharger l'enregistrement depuis la base de données pour avoir les dernières données
        $this->record->refresh();
        
        // Forcer le calcul des totaux
        if ($this->record && $this->record->exists) {
            try {
                // Recalculer les totaux pour s'assurer qu'ils sont à jour
                $this->record->calculateTotals();
                
                // Charger les relations pour un affichage complet
                $this->record->load(['items.product', 'items.transactionUnit']);
                
                // Journaliser le rafraîchissement
                \Illuminate\Support\Facades\Log::info("[ViewCreditNote] Rafraîchissement des données pour l'avoir #{$this->record->credit_note_number}", [
                    'items_count' => $this->record->items->count(),
                    'subtotal' => $this->record->subtotal,
                    'taxes' => $this->record->taxes_amount,
                    'total' => $this->record->total_amount
                ]);
            } catch (\Exception $e) {
                // Logger l'erreur mais continuer l'exécution
                \Illuminate\Support\Facades\Log::error("Erreur lors du calcul des totaux dans ViewCreditNote: " . $e->getMessage());
            }
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn ($record) => in_array($record->status, ['draft', 'issued'])),
            Actions\Action::make('issue')
                ->label('Émettre')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn ($record) => $record->status === 'draft')
                ->action(function () {
                    $this->record->status = 'issued';
                    $this->record->save();
                    
                    // Recharger l'enregistrement et ses relations
                    $this->record->refresh();
                    $this->record->load(['items.product', 'items.transactionUnit']);
                    
                    // Recalculer les totaux après le changement de statut
                    try {
                        $this->record->calculateTotals();
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error("Erreur lors du calcul des totaux après émission: " . $e->getMessage());
                    }
                    
                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                }),
            Actions\Action::make('void')
                ->label('Annuler')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn ($record) => in_array($record->status, ['draft', 'issued']))
                ->action(function () {
                    $this->record->status = 'voided';
                    $this->record->save();
                    
                    // Recharger l'enregistrement et ses relations
                    $this->record->refresh();
                    $this->record->load(['items.product', 'items.transactionUnit']);
                    
                    // Recalculer les totaux après le changement de statut
                    try {
                        $this->record->calculateTotals();
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error("Erreur lors du calcul des totaux après annulation: " . $e->getMessage());
                    }
                    
                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                }),
            Actions\Action::make('print')
                ->label('Imprimer')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(function () {
                    // Utiliser la même approche que pour les factures
                    $protocol = request()->secure() ? 'https://' : 'http://';
                    $host = request()->getHost();
                    $port = ':8000'; // Ajouter le port pour le développement local
                    
                    // Route avec paramètre pour générer le PDF de l'avoir
                    return $protocol . $host . $port . '/credit-notes/' . $this->record->id . '/pdf';
                })
                ->openUrlInNewTab(),
        ];
    }
}
