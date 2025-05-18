<?php

namespace App\Filament\Company\Resources\GoodsReceiptResource\Pages;

use App\Filament\Company\Resources\GoodsReceiptResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use App\Models\GoodsReceipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EditGoodsReceipt extends EditRecord
{
    protected static string $resource = GoodsReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('validate_receipt')
                ->label('Valider la Réception')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->visible(fn (GoodsReceipt $record): bool => 
                    in_array($record->status, ['draft', 'pending_validation']) &&
                    $record->items->count() > 0
                )
                ->action(function (GoodsReceipt $record): void {
                    try {
                        DB::transaction(function () use ($record) {
                            // Vérifier que tous les items ont des quantités valides
                            $invalidItems = $record->items()
                                ->whereNull('quantity_received')
                                ->orWhere('quantity_received', 0)
                                ->get();

                            if ($invalidItems->isNotEmpty()) {
                                $itemsList = $invalidItems->map(fn ($item) => 
                                    $item->product?->name ?? "Item #{$item->id}"
                                )->join(', ');

                                Notification::make()
                                    ->title('Validation impossible')
                                    ->body("Certains articles n'ont pas de quantité reçue : {$itemsList}")
                                    ->danger()
                                    ->send();
                                return;
                            }

                            // Mettre à jour le statut
                            $record->status = 'validated';
                            $record->validated_at = now();
                            $record->validated_by_user_id = auth()->id();
                            $record->save();

                            Log::info('[GoodsReceipt Validation] BR validée', [
                                'receipt_number' => $record->receipt_number,
                                'items_count' => $record->items->count(),
                                'validated_by' => auth()->id()
                            ]);

                            Notification::make()
                                ->title('Réception validée')
                                ->body('Le stock a été mis à jour pour tous les articles.')
                                ->success()
                                ->send();

                            $this->refreshFormData(['status']);
                        });
                    } catch (\Exception $e) {
                        Log::error('[GoodsReceipt Validation] Erreur lors de la validation', [
                            'receipt_number' => $record->receipt_number,
                            'error' => $e->getMessage()
                        ]);

                        Notification::make()
                            ->title('Erreur lors de la validation')
                            ->body("Une erreur est survenue : {$e->getMessage()}")
                            ->danger()
                            ->send();
                    }
                })
                ->requiresConfirmation()
                ->modalHeading('Valider la réception ?')
                ->modalDescription('Cette action va mettre à jour les stocks des produits. Cette opération ne peut pas être annulée. Êtes-vous sûr ?')
                ->modalSubmitActionLabel('Oui, valider')
                ->modalCancelActionLabel('Non, annuler'),

            Actions\DeleteAction::make()
                ->visible(fn (GoodsReceipt $record): bool => 
                    !in_array($record->status, ['validated', 'completed'])
                ),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
