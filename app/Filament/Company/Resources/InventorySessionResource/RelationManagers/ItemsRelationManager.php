<?php

namespace App\Filament\Company\Resources\InventorySessionResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Product;
use App\Models\InventorySessionItem;
use App\Models\InventorySession;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model; // Pour le typage de $ownerRecord
use Illuminate\Database\Eloquent\Builder;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $recordTitleAttribute = 'product.name'; // Essayons d'utiliser le nom du produit

    // Détermine si le RelationManager peut être utilisé pour créer/modifier des items
    // en fonction du statut de la session d'inventaire parente.
    protected function canCreate(): bool
    {
        $ownerRecord = $this->getOwnerRecord();
        return $ownerRecord && in_array($ownerRecord->status, ['draft', 'in_progress']);
    }

    protected function canEdit(Model $record): bool
    {
        $ownerRecord = $this->getOwnerRecord();
        return $ownerRecord && in_array($ownerRecord->status, ['draft', 'in_progress']);
    }

    protected function canDelete(Model $record): bool
    {
        $ownerRecord = $this->getOwnerRecord();
        return $ownerRecord && in_array($ownerRecord->status, ['draft', 'in_progress']);
    }
    
    protected function canDeleteAny(): bool
    {
        $ownerRecord = $this->getOwnerRecord();
        return $ownerRecord && in_array($ownerRecord->status, ['draft', 'in_progress']);
    }


    public function form(Form $form): Form
    {
        /** @var InventorySession $ownerRecord */
        $ownerRecord = $this->getOwnerRecord();

        return $form
            ->schema([
                Select::make('product_id')
                    ->label('Produit')
                    ->relationship('product', 'name', modifyQueryUsing: fn ($query) => $query->orderBy('name'))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function (callable $set, ?string $state) {
                        if ($state) {
                            $product = Product::find($state);
                            if ($product) {
                                // Remplir la quantité théorique avec le stock actuel du produit
                                $set('theoretical_quantity', $product->stock_quantity);
                            }
                        } else {
                            $set('theoretical_quantity', 0);
                        }
                    })
                    // Empêche de sélectionner un produit déjà dans cette session d'inventaire
                    // Utilisons une approche plus simple pour la validation d'unicité
                    ->rules([
                        function () use ($ownerRecord) {
                            return function (string $attribute, $value, \Closure $fail) use ($ownerRecord) {
                                // Vérifier si le produit est déjà dans cette session d'inventaire
                                $exists = \App\Models\InventorySessionItem::query()
                                    ->where('inventory_session_id', $ownerRecord->id)
                                    ->where('product_id', $value)
                                    ->exists();
                                
                                if ($exists) {
                                    $fail("Ce produit est déjà dans cette session d'inventaire.");
                                }
                            };
                        }
                    ])
                    ->columnSpan(2)
                    ->disabled(fn (string $operation): bool => $operation === 'edit'), // Ne pas changer le produit en édition

                TextInput::make('theoretical_quantity')
                    ->label('Qté Théorique (Système)')
                    ->numeric()
                    ->disabled() // Ce champ est rempli automatiquement
                    ->dehydrated() // S'assurer qu'il est sauvegardé
                    ->required(),

                TextInput::make('counted_quantity')
                    ->label('Qté Comptée')
                    ->numeric()
                    ->nullable() // Peut être null tant que le comptage n'est pas fait
                    ->required(fn() => $ownerRecord->status === 'completed' || $ownerRecord->status === 'validated') // Requis si comptage terminé
                    ->rules(['gte:0']) // Doit être >= 0
                    ->columnSpan(fn() => $ownerRecord->status === 'draft' ? 2 : 1) // Prend plus de place en brouillon
                    ->disabled(fn() => !in_array($ownerRecord->status, ['draft', 'in_progress'])),


                // Afficher l'écart seulement si la quantité comptée est renseignée
                Placeholder::make('difference_quantity_placeholder')
                    ->label('Écart')
                    ->content(function (Forms\Get $get): ?string {
                        $counted = $get('counted_quantity');
                        $theoretical = $get('theoretical_quantity');
                        if ($counted !== null && $theoretical !== null) {
                            $diff = (float)$counted - (float)$theoretical;
                            $sign = $diff > 0 ? '+' : '';
                            return $sign . number_format($diff, 2, ',', ' ') . 
                                   ($diff != 0 ? ($diff > 0 ? ' (Surplus)' : ' (Manquant)') : ' (OK)');
                        }
                        return null;
                    })
                    ->visible(fn (Forms\Get $get) => $get('counted_quantity') !== null && $ownerRecord->status !== 'draft'),


                Forms\Components\Textarea::make('item_notes')
                    ->label('Notes sur l\'item')
                    ->rows(2)
                    ->columnSpanFull(),
            ])->columns(2);
    }

    public function table(Table $table): Table
    {
        /** @var InventorySession $ownerRecord */
        $ownerRecord = $this->getOwnerRecord();

        return $table
            ->recordTitleAttribute('product.name')
            ->columns([
                TextColumn::make('product.name')
                    ->label('Produit')
                    ->searchable(isIndividual: true, isGlobal: false) // Recherche sur ce champ spécifiquement
                    ->sortable(),
                TextColumn::make('product.sku')
                    ->label('SKU')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('theoretical_quantity')
                    ->label('Qté Théorique')
                    ->numeric(decimalPlaces: 2)
                    ->alignRight(),
                TextColumn::make('counted_quantity')
                    ->label('Qté Comptée')
                    ->numeric(decimalPlaces: 2)
                    ->alignRight()
                    ->color(fn (InventorySessionItem $record) => $record->counted_quantity === null ? 'gray' : null),
                TextColumn::make('difference_quantity') // S'appuie sur la colonne `storedAs` ou l'accesseur
                    ->label('Écart')
                    ->numeric(decimalPlaces: 2)
                    ->alignRight()
                    ->color(fn (?string $state): string => match (true) {
                        (float)$state > 0 => 'success', // Surplus
                        (float)$state < 0 => 'danger',  // Manquant
                        default => 'gray',              // Pas d'écart ou non compté
                    })
                    ->formatStateUsing(function (?string $state): string {
                        if ($state === null) return '-';
                        $floatState = (float)$state;
                        $sign = $floatState > 0 ? '+' : '';
                        return $sign . number_format($floatState, 2, ',', ' ');
                    }),
                TextColumn::make('item_notes')->label('Notes Item')->limit(30)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // On pourrait ajouter un filtre pour voir les items avec écarts, etc.
                Tables\Filters\Filter::make('has_difference')
                    ->label('Avec Écart')
                    ->query(fn (Builder $query): Builder => $query->whereRaw('counted_quantity != theoretical_quantity AND counted_quantity IS NOT NULL')),
                Tables\Filters\Filter::make('not_counted')
                    ->label('Non Compté')
                    ->query(fn (Builder $query): Builder => $query->whereNull('counted_quantity')),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn() => $this->canCreate())
                    // ->mutateFormDataUsing(function (array $data): array {
                    //     // theoretical_quantity est déjà géré par afterStateUpdated du product_id
                    //     return $data;
                    // }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->visible(fn(InventorySessionItem $record) => $this->canEdit($record)),
                Tables\Actions\DeleteAction::make()->visible(fn(InventorySessionItem $record) => $this->canDelete($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->visible(fn() => $this->canDeleteAny()),
                ]),
            ])
            ->defaultSort('product.name', 'asc');
    }
}
