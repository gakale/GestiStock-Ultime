<?php

namespace App\Filament\Company\Resources\InventorySessionResource\Pages;

use App\Filament\Company\Resources\InventorySessionResource;
use App\Models\InventorySession; // Importer le modèle
use App\Models\InventorySessionItem; // Importer le modèle
use App\Models\Product;
use App\Models\StockMovement;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB; // Pour les transactions
use Filament\Notifications\Notification; // Pour les notifications

class EditInventorySession extends EditRecord
{
    protected static string $resource = InventorySessionResource::class;

    protected function getHeaderActions(): array
    {
        /** @var InventorySession $record */
        $record = $this->getRecord();

        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()->visible(fn() => in_array($record->status, ['draft', 'cancelled'])),

            Actions\Action::make('start_counting')
                ->label('Démarrer le Comptage')
                ->color('warning')
                ->icon('heroicon-o-play-circle')
                ->visible(fn() => $record->status === 'draft' && $record->items()->count() > 0)
                ->action(function () use ($record) {
                    if ($record->items()->count() === 0) {
                        Notification::make()
                            ->title('Aucun article')
                            ->body('Veuillez ajouter des articles à la session avant de démarrer le comptage.')
                            ->danger()
                            ->send();
                        return;
                    }
                    $record->status = 'in_progress';
                    $record->save();
                    Notification::make()->title('Comptage démarré')->success()->send();
                    $this->refreshFormData(['status']); // Rafraîchir le champ statut
                })
                ->requiresConfirmation()
                ->modalHeading('Démarrer le comptage ?')
                ->modalDescription('Une fois le comptage démarré, vous ne pourrez plus ajouter ou supprimer des produits de cette session.'),

            Actions\Action::make('complete_counting')
                ->label('Terminer le Comptage')
                ->color('info')
                ->icon('heroicon-o-check-circle')
                ->visible(fn() => $record->status === 'in_progress')
                ->action(function () use ($record) {
                    // Vérifier si tous les items ont une quantité comptée (optionnel, mais bonne pratique)
                    $uncountedItems = $record->items()->whereNull('counted_quantity')->count();
                    if ($uncountedItems > 0) {
                        Notification::make()
                            ->title('Articles non comptés')
                            ->body("Il reste {$uncountedItems} article(s) sans quantité comptée. Veuillez les compléter ou valider quand même.")
                            ->warning()
                            ->send();
                        // On pourrait empêcher de terminer ici, ou juste avertir
                    }
                    $record->status = 'completed';
                    $record->save();
                    Notification::make()->title('Comptage terminé')->success()->send();
                    $this->refreshFormData(['status']);
                })
                ->requiresConfirmation(),

            Actions\Action::make('validate_inventory')
                ->label('Valider l\'Inventaire')
                ->color('success')
                ->icon('heroicon-o-document-check')
                ->visible(fn() => $record->status === 'completed')
                ->action('processInventoryValidation') // Appelle la méthode publique ci-dessous
                ->requiresConfirmation()
                ->modalHeading('Valider l\'inventaire et mettre à jour le stock ?')
                ->modalDescription('Cette action mettra à jour les quantités en stock des produits concernés en fonction des écarts constatés. Cette action est irréversible.'),

            Actions\Action::make('cancel_session')
                ->label('Annuler la Session')
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->visible(fn() => !in_array($record->status, ['validated', 'cancelled']))
                ->action(function () use ($record) {
                    // Si l'inventaire était validé, il faudrait une logique d'annulation plus complexe
                    // pour inverser les mouvements de stock. Pour l'instant, on annule avant validation.
                    $record->status = 'cancelled';
                    $record->save();
                    Notification::make()->title('Session annulée')->success()->send();
                    $this->refreshFormData(['status']);
                })
                ->requiresConfirmation(),
        ];
    }

    // Méthode pour traiter la validation de l'inventaire
    public function processInventoryValidation(): void
    {
        /** @var InventorySession $session */
        $session = $this->getRecord();

        if ($session->status !== 'completed') {
            Notification::make()
                ->title('Action non permise')
                ->body('L\'inventaire doit être au statut "Comptage terminé" pour être validé.')
                ->danger()
                ->send();
            return;
        }

        try {
            DB::transaction(function () use ($session) {
                foreach ($session->items as $item) {
                    /** @var InventorySessionItem $item */
                    if ($item->counted_quantity === null) {
                        // Si un item n'a pas été compté, on le saute ou on le considère comme 0 ou comme théorique
                        // Pour l'instant, on le saute, mais il faudrait une règle claire.
                        // Ou s'assurer que tous les items sont comptés avant de valider.
                        continue; 
                    }

                    $difference = (float)$item->counted_quantity - (float)$item->theoretical_quantity;

                    if ($difference != 0) {
                        $product = $item->product;
                        if ($product) {
                            $oldStock = $product->stock_quantity;
                            $product->stock_quantity = (float)$item->counted_quantity; // Le nouveau stock est la quantité comptée
                            $product->save(); // Sauvegarde le produit avec le nouveau stock

                            StockMovement::create([
                                'product_id' => $item->product_id,
                                'type' => 'inventory_adjustment',
                                'quantity_changed' => $difference,
                                'new_stock_quantity_after_movement' => $product->stock_quantity,
                                'related_document_type' => InventorySession::class,
                                'related_document_id' => $session->id,
                                'user_id' => $session->user_id, // Ou auth()->id()
                                'movement_date' => $session->inventory_date,
                                'reason' => 'Ajustement d\'inventaire: ' . $session->reference_number,
                                'notes' => "Théorique: {$item->theoretical_quantity}, Compté: {$item->counted_quantity}",
                            ]);
                        }
                    }
                }

                $session->status = 'validated';
                $session->save();
            });

            Notification::make()
                ->title('Inventaire Validé')
                ->body('Les stocks ont été mis à jour avec succès.')
                ->success()
                ->send();
            
            $this->refreshFormData(['status']); // Rafraîchir les données du formulaire, y compris le statut

        } catch (\Exception $e) {
            Notification::make()
                ->title('Erreur lors de la validation')
                ->body('Une erreur est survenue: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }
}
