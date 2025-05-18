<?php

namespace App\Filament\Company\Resources\SupplierCreditNoteResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Product;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Collection;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Placeholder;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Filament\Support\RawJs;
use App\Models\InventorySessionItem;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    // protected static ?string $recordTitleAttribute = 'product_id'; // On va le rendre plus descriptif

    /**
     * Retourne le schéma de formulaire sous forme de tableau pour être utilisé dans d'autres contextes
     * Cette méthode est appelée depuis SupplierCreditNoteResource
     */
    public static function getFormSchemaArray(): array
    {
        return [
            Select::make('product_id')
                ->label('Produit')
                ->relationship('product', 'name')
                ->searchable()
                ->preload()
                ->required()
                ->reactive()
                ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                    if ($state) {
                        $product = Product::find($state);
                        if ($product) {
                            $set('description', $product->description);
                            $set('unit_price', $product->purchase_price);
                            $set('tax_rate', $product->tax_rate ?? 20);
                        }
                    }
                }),

            TextInput::make('description')
                ->label('Description')
                ->required()
                ->maxLength(255),

            TextInput::make('quantity')
                ->label('Quantité')
                ->numeric()
                ->default(1)
                ->minValue(0.01)
                ->required()
                ->reactive(),

            TextInput::make('unit_price')
                ->label('Prix unitaire')
                ->numeric()
                ->prefix('€')
                ->required()
                ->reactive(),

            TextInput::make('discount_percentage')
                ->label('Remise (%)')
                ->numeric()
                ->default(0)
                ->minValue(0)
                ->maxValue(100)
                ->suffix('%')
                ->reactive(),

            TextInput::make('tax_rate')
                ->label('TVA (%)')
                ->numeric()
                ->default(20)
                ->minValue(0)
                ->maxValue(100)
                ->suffix('%')
                ->reactive(),

            Placeholder::make('line_total')
                ->label('Total ligne')
                ->content(function (Get $get): string {
                    $quantity = (float) ($get('quantity') ?? 0);
                    $unitPrice = (float) ($get('unit_price') ?? 0);
                    $discountPercentage = (float) ($get('discount_percentage') ?? 0);
                    $taxRate = (float) ($get('tax_rate') ?? 0);

                    $subtotal = $quantity * $unitPrice;
                    $discount = $subtotal * ($discountPercentage / 100);
                    $afterDiscount = $subtotal - $discount;
                    $tax = $afterDiscount * ($taxRate / 100);
                    $total = $afterDiscount + $tax;

                    return number_format($total, 2, ',', ' ') . ' €';
                }),
        ];
    }

    public function form(Form $form): Form
    {
        /** @var \App\Models\InventorySession $ownerRecord */
        $ownerRecord = $this->getOwnerRecord();

        return $form
            ->schema([
                Select::make('product_id')
                    ->label('Produit')
                    ->relationship('product', 'name', fn ($query) => $query->orderBy('name'))
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
                    ->unique(
                        ignoreRecord: true,
                        callback: function (Forms\Components\Select $component, $rule) use ($ownerRecord) {
                            return $rule->where('inventory_session_id', $ownerRecord->id);
                        }
                    )
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
        /** @var \App\Models\InventorySession $ownerRecord */
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
                    ->color(fn ($record) => $record->counted_quantity === null ? 'gray' : null),
                TextColumn::make('difference_quantity') // S'appuie sur la colonne `storedAs` ou l'accesseur
                    ->label('Écart')
                    ->numeric(decimalPlaces: 2)
                    ->alignRight()
                    ->color(fn (?string $state): string => match (true) {
                        is_null($state) => 'gray',
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
                Tables\Actions\EditAction::make()->visible(fn($record) => $this->canEdit($record)),
                Tables\Actions\DeleteAction::make()->visible(fn($record) => $this->canDelete($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->visible(fn() => $this->canDeleteAny()),
                ]),
            ])
            ->defaultSort('product.name', 'asc');
    }
}