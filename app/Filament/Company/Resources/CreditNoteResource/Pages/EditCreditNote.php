<?php

namespace App\Filament\Company\Resources\CreditNoteResource\Pages;

use App\Filament\Company\Resources\CreditNoteResource;
use App\Models\CreditNote;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCreditNote extends EditRecord
{
    protected static string $resource = CreditNoteResource::class;
    
    public function mount(int|string $record): void
    {
        parent::mount($record);
        
        // Forcer le calcul des totaux lors du chargement de la page d'édition
        if ($this->record && $this->record->exists) {
            try {
                // Recalculer les totaux pour s'assurer qu'ils sont à jour
                $this->record->calculateTotals();
                
                // Charger les relations pour s'assurer que les données du repeater sont complètes
                $this->record->load(['items.product', 'items.transactionUnit']);
            } catch (\Exception $e) {
                // Logger l'erreur mais continuer l'exécution
                \Illuminate\Support\Facades\Log::error("Erreur lors du calcul des totaux: " . $e->getMessage());
            }
        }
    }
    
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Charger les items manuellement pour s'assurer qu'ils sont correctement formatés pour le repeater
        if (isset($data['id'])) {
            $creditNote = CreditNote::with(['items.product', 'items.transactionUnit'])->find($data['id']);
            
            if ($creditNote && $creditNote->items->count() > 0) {
                $data['items'] = $creditNote->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'description' => $item->description,
                        'quantity' => (float)$item->quantity,
                        'unit_price' => (float)$item->unit_price,
                        'discount_percentage' => (float)$item->discount_percentage,
                        'tax_rate' => (float)$item->tax_rate,
                        'line_total' => (float)$item->line_total,
                        'transaction_unit_id' => $item->transaction_unit_id,
                    ];
                })->toArray();
            }
        }
        
        return $data;
    }

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
                    
                    // Recharger l'enregistrement et ses relations
                    $this->record->refresh();
                    $this->record->load(['items.product', 'items.transactionUnit']);
                    
                    // Recalculer les totaux après l'émission
                    try {
                        $this->record->calculateTotals();
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error("Erreur lors du calcul des totaux après émission: " . $e->getMessage());
                    }
                    
                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                }),
        ];
    }
    
    protected function getRedirectUrl(): string
    {
        // Rediriger vers la page de visualisation plutôt que l'index
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
    
    protected function afterSave(): void
    {
        // Recalculer les totaux après la sauvegarde
        if ($this->record) {
            // Recharger l'enregistrement et ses relations
            $this->record->refresh();
            $this->record->load(['items.product', 'items.transactionUnit']);
            
            try {
                $this->record->calculateTotals();
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Erreur lors du calcul des totaux après sauvegarde: " . $e->getMessage());
            }
        }
    }
}
