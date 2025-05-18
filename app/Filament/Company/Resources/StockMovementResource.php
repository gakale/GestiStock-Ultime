<?php

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\StockMovementResource\Pages;
// use App\Filament\Company\Resources\StockMovementResource\RelationManagers;
use App\Models\StockMovement;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

use Filament\Tables\Columns\TextColumn;

class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Stocks';
    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'type'; // Ou une combinaison plus descriptive

    // On ne veut pas de création/édition directe de mouvements pour l'instant
    public static function canCreate(): bool { return false; }
    // public static function canEdit(Model $record): bool { return false; } // Si on active la page de vue, l'édition pourrait être désactivée


    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('movement_date', 'desc') // Tri par défaut par date décroissante
            ->columns([
                TextColumn::make('movement_date')
                    ->label('Date et Heure')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('product.name')
                    ->label('Produit')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Type')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('quantity_changed')
                    ->label('Qté Modifiée')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('unit.name')
                    ->label('Unité')
                    ->sortable()
                    ->searchable()
                    ->placeholder('N/A'),
                TextColumn::make('new_stock_quantity_after_movement')
                    ->label('Nouveau Stock')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('user.name') // Assurez-vous que la relation 'user' existe sur StockMovement
                    ->label('Opérateur')
                    ->searchable()
                    ->sortable()
                    ->placeholder('N/A'),
                TextColumn::make('related_document_type')
                    ->label('Doc. Lié (Type)')
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : 'N/A')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reason')
                    ->label('Raison')
                    ->limit(30)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('movement_date', 'desc') // Trier par date la plus récente par défaut
            ->filters([
                Tables\Filters\SelectFilter::make('product_id')
                    ->label('Produit')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('type')
                    ->label('Type de Mouvement')
                    ->options([ // À remplir avec vos types
                        'purchase_receipt' => 'Réception Fournisseur',
                        'purchase_receipt_cancellation' => 'Annulation Réception Fourn.',
                        // ... autres types à venir
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(), // Permettre de voir les détails du mouvement
            ])
            ->bulkActions([
                // Pas d'actions de masse pour l'instant
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        // Si vous avez utilisé --generate ou --view, ces pages devraient exister
        return [
            'index' => Pages\ListStockMovements::route('/'),
            'view' => Pages\ViewStockMovement::route('/{record}'), // Si vous voulez une page de vue dédiée
        ];
    }
}