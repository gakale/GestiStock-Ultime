<?php

declare(strict_types=1);

namespace App\Filament\Company\Resources\PurchaseOrderResource\RelationManagers;

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

    protected static ?string $recordTitleAttribute = 'product_name';

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
                                // Pré-remplir les champs avec les données du produit
                                $set('product_name', $product->name);
                                $set('product_sku', $product->sku);
                                $set('unit_price', $product->purchase_price);
                                
                                // Pré-remplir l'unité de transaction avec l'unité d'achat par défaut du produit
                                $set('transaction_unit_id', $product->purchase_unit_id ?? $product->stock_unit_id);
                            }
                        } else {
                            // Réinitialiser les champs si aucun produit n'est sélectionné
                            $set('product_name', null);
                            $set('product_sku', null);
                            $set('unit_price', null);
                            $set('transaction_unit_id', null);
                        }
                    }),
                
                TextInput::make('product_name')
                    ->label('Nom du produit')
                    ->required(),
                
                TextInput::make('product_sku')
                    ->label('SKU')
                    ->nullable(),
                
                Select::make('transaction_unit_id')
                    ->label('Unité de Commande')
                    ->options(function (Get $get) {
                        $productId = $get('product_id');
                        if (!$productId) {
                            return [];
                        }
                        
                        $product = Product::find($productId);
                        if (!$product) {
                            return [];
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
                    ->reactive(),
                
                TextInput::make('quantity')
                    ->label('Quantité Commandée')
                    ->numeric()
                    ->required()
                    ->default(1)
                    ->reactive(),
                
                TextInput::make('unit_price')
                    ->label('Prix Unitaire HT (par unité de cmd)')
                    ->numeric()
                    ->required()
                    ->prefix('€')
                    ->reactive(),
                
                TextInput::make('discount_percentage')
                    ->label('Remise (%)')
                    ->numeric()
                    ->default(0)
                    ->minValue(0)
                    ->maxValue(100)
                    ->reactive(),
                
                TextInput::make('tax_rate')
                    ->label('Taux de TVA (%)')
                    ->numeric()
                    ->default(20)
                    ->reactive(),
                
                TextInput::make('line_total')
                    ->label('Total Ligne')
                    ->numeric()
                    ->prefix('€')
                    ->disabled()
                    ->dehydrated()
                    ->afterStateHydrated(function (Get $get, Set $set) {
                        // Calculer le total de la ligne à l'affichage
                        $quantity = (float) $get('quantity');
                        $unitPrice = (float) $get('unit_price');
                        $discount = (float) $get('discount_percentage');
                        $tax = (float) $get('tax_rate');
                        
                        $basePrice = $quantity * $unitPrice;
                        $discountAmount = $basePrice * ($discount / 100);
                        $priceAfterDiscount = $basePrice - $discountAmount;
                        $taxAmount = $priceAfterDiscount * ($tax / 100);
                        $total = $priceAfterDiscount + $taxAmount;
                        
                        $set('line_total', round($total, 2));
                    }),
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
                
                TextColumn::make('transactionUnit.name_with_symbol')
                    ->label('Unité Cmd.')
                    ->sortable(),
                
                TextColumn::make('quantity')
                    ->label('Qté Cmdée')
                    ->numeric(
                        decimalPlaces: 2,
                        decimalSeparator: ',',
                        thousandsSeparator: ' ',
                    )
                    ->sortable(),
                
                TextColumn::make('unit_price')
                    ->label('PU HT Cmd.')
                    ->money('eur')
                    ->sortable(),
                
                TextColumn::make('discount_percentage')
                    ->label('Remise')
                    ->numeric(
                        decimalPlaces: 2,
                        decimalSeparator: ',',
                        thousandsSeparator: ' ',
                    )
                    ->suffix('%')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                TextColumn::make('tax_rate')
                    ->label('TVA')
                    ->numeric(
                        decimalPlaces: 2,
                        decimalSeparator: ',',
                        thousandsSeparator: ' ',
                    )
                    ->suffix('%')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                TextColumn::make('line_total')
                    ->label('Total TTC')
                    ->money('eur')
                    ->sortable(),
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
