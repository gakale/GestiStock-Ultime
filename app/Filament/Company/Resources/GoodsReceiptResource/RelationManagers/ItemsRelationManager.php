<?php

declare(strict_types=1);

namespace App\Filament\Company\Resources\GoodsReceiptResource\RelationManagers;

use App\Models\Product;
use App\Models\UnitOfMeasure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Get;
use Filament\Forms\Set;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $recordTitleAttribute = 'id';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('product_id')
                    ->label('Produit')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function (Set $set, ?string $state) {
                        if ($state) {
                            $product = Product::find($state);
                            if ($product) {
                                // Pré-remplir l'unité de transaction avec l'unité d'achat par défaut du produit
                                $set('transaction_unit_id', $product->purchase_unit_id ?? $product->stock_unit_id);
                                $set('unit_price', $product->purchase_price); // Si le prix vient du produit
                            }
                        }
                    })
                    ->columnSpan(2),

                TextInput::make('quantity_ordered')
                    ->label('Qté Commandée')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false),

                Select::make('transaction_unit_id')
                    ->label('Unité Reçue')
                    ->options(function (Get $get) {
                        $productId = $get('product_id');
                        if (!$productId) {
                            return UnitOfMeasure::where('is_active', true)->pluck('name_with_symbol', 'id');
                        }
                        
                        $product = Product::find($productId);
                        if (!$product) {
                            return UnitOfMeasure::where('is_active', true)->pluck('name_with_symbol', 'id');
                        }
                        
                        // Récupérer toutes les unités compatibles avec ce produit
                        $unitIds = [];
                        
                        // Ajouter l'unité d'achat si définie
                        if ($product->purchase_unit_id) {
                            $unitIds[] = $product->purchase_unit_id;
                            
                            // Ajouter les unités dérivées de l'unité d'achat
                            $derivedUnits = UnitOfMeasure::where('base_unit_id', $product->purchase_unit_id)->pluck('id')->toArray();
                            $unitIds = array_merge($unitIds, $derivedUnits);
                        }
                        
                        // Ajouter l'unité de stock si différente de l'unité d'achat
                        if ($product->stock_unit_id && !in_array($product->stock_unit_id, $unitIds)) {
                            $unitIds[] = $product->stock_unit_id;
                            
                            // Ajouter les unités dérivées de l'unité de stock
                            $derivedUnits = UnitOfMeasure::where('base_unit_id', $product->stock_unit_id)->pluck('id')->toArray();
                            $unitIds = array_merge($unitIds, $derivedUnits);
                        }
                        
                        return UnitOfMeasure::whereIn('id', $unitIds)
                            ->get()
                            ->pluck('name_with_symbol', 'id')
                            ->toArray();
                    })
                    ->searchable()
                    ->preload()
                    ->required()
                    ->reactive()
                    ->helperText('L\'unité dans laquelle la quantité est saisie.'),
                    

                TextInput::make('transaction_quantity')
                    ->label('Qté Reçue (en unité transaction)')
                    ->numeric()
                    ->default(0)
                    ->required()
                    ->minValue(0)
                    ->step(0.01),
                
                TextInput::make('unit_price')
                    ->label('Prix Unitaire Achat')
                    ->numeric()
                    ->prefix('€')
                    ->nullable(),

                Textarea::make('notes')
                    ->label('Notes Item')
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')
                    ->label('Produit')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('quantity_ordered')
                    ->label('Qté Cmdée')
                    ->numeric()
                    ->alignRight(),
                TextColumn::make('transaction_quantity')
                    ->label('Qté Reçue Transac.')
                    ->numeric()
                    ->alignRight(),
                TextColumn::make('transactionUnit.symbol')
                    ->label('Unité Transac.'),
                TextColumn::make('quantity_received')
                    ->label('Qté Reçue Stock')
                    ->numeric()
                    ->alignRight()
                    ->description(fn ($record) => $record->product?->stockUnit?->symbol),
                TextColumn::make('unit_price')
                    ->label('PU Achat')
                    ->money('eur')
                    ->alignRight(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
