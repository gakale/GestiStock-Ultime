<?php

declare(strict_types=1);

namespace App\Filament\Company\Resources\InvoiceResource\RelationManagers;

use App\Models\Product;
use App\Models\UnitOfMeasure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
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
                Forms\Components\Grid::make(3)
                    ->schema([
                        Forms\Components\Select::make('product_id')
                            ->label('Produit')
                            ->relationship('product', 'name', fn (Builder $query) => $query->where('is_active', true))
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function (Set $set, ?string $state, Get $get) {
                                if ($state) {
                                    $product = Product::find($state);
                                    $set('unit_price', $product?->selling_price ?? 0);
                                    $set('product_name', $product?->name); // Copie
                                    $set('product_sku', $product?->sku);   // Copie
                                    
                                    // Définir l'unité de vente par défaut du produit
                                    if ($product && $product->sales_unit_id) {
                                        $set('transaction_unit_id', $product->sales_unit_id);
                                    }
                                } else { // Si le produit est déselectionné
                                    $set('unit_price', 0);
                                    $set('product_name', null);
                                    $set('product_sku', null);
                                    $set('transaction_unit_id', null);
                                }
                            })
                            ->required()
                            ->columnSpan(2),
                            
                        Forms\Components\Select::make('transaction_unit_id')
                            ->label('Unité')
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
                                // Cela inclut l'unité de stock, l'unité d'achat, l'unité de vente et leurs unités dérivées
                                $unitIds = [];
                                
                                // Ajouter l'unité de vente si définie
                                if ($product->sales_unit_id) {
                                    $unitIds[] = $product->sales_unit_id;
                                    
                                    // Ajouter les unités dérivées de l'unité de vente
                                    $derivedUnits = UnitOfMeasure::where('base_unit_id', $product->sales_unit_id)->pluck('id')->toArray();
                                    $unitIds = array_merge($unitIds, $derivedUnits);
                                }
                                
                                // Ajouter l'unité de stock si différente de l'unité de vente
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
                            ->reactive()
                            ->required()
                            ->columnSpan(1),
                    ]),
                
                Forms\Components\Hidden::make('product_name'),
                Forms\Components\Hidden::make('product_sku'),
                
                Forms\Components\Textarea::make('description')
                    ->label('Description')
                    ->rows(2)
                    ->columnSpanFull(),
                
                Forms\Components\Grid::make(4)
                    ->schema([
                        Forms\Components\TextInput::make('quantity')
                            ->label('Quantité')
                            ->numeric()
                            ->default(1)
                            ->minValue(0.01)
                            ->step(0.01)
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function (Set $set, Get $get) {
                                $quantity = (float)($get('quantity') ?? 0);
                                $unitPrice = (float)($get('unit_price') ?? 0);
                                $discountPercentage = (float)($get('discount_percentage') ?? 0);
                                $taxRate = (float)($get('tax_rate') ?? 0);
                                
                                $basePrice = $quantity * $unitPrice;
                                $discountAmount = $basePrice * ($discountPercentage / 100);
                                $priceAfterDiscount = $basePrice - $discountAmount;
                                $taxAmount = $priceAfterDiscount * ($taxRate / 100);
                                $lineTotal = $priceAfterDiscount + $taxAmount;
                                
                                $set('line_total', $lineTotal);
                            }),
                            
                        Forms\Components\TextInput::make('unit_price')
                            ->label('Prix unitaire HT')
                            ->numeric()
                            ->required()
                            ->prefix(config('app.currency_symbol', '€'))
                            ->reactive()
                            ->afterStateUpdated(function (Set $set, Get $get) {
                                $quantity = (float)($get('quantity') ?? 0);
                                $unitPrice = (float)($get('unit_price') ?? 0);
                                $discountPercentage = (float)($get('discount_percentage') ?? 0);
                                $taxRate = (float)($get('tax_rate') ?? 0);
                                
                                $basePrice = $quantity * $unitPrice;
                                $discountAmount = $basePrice * ($discountPercentage / 100);
                                $priceAfterDiscount = $basePrice - $discountAmount;
                                $taxAmount = $priceAfterDiscount * ($taxRate / 100);
                                $lineTotal = $priceAfterDiscount + $taxAmount;
                                
                                $set('line_total', $lineTotal);
                            }),
                            
                        Forms\Components\TextInput::make('discount_percentage')
                            ->label('Remise %')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->reactive()
                            ->afterStateUpdated(function (Set $set, Get $get) {
                                $quantity = (float)($get('quantity') ?? 0);
                                $unitPrice = (float)($get('unit_price') ?? 0);
                                $discountPercentage = (float)($get('discount_percentage') ?? 0);
                                $taxRate = (float)($get('tax_rate') ?? 0);
                                
                                $basePrice = $quantity * $unitPrice;
                                $discountAmount = $basePrice * ($discountPercentage / 100);
                                $priceAfterDiscount = $basePrice - $discountAmount;
                                $taxAmount = $priceAfterDiscount * ($taxRate / 100);
                                $lineTotal = $priceAfterDiscount + $taxAmount;
                                
                                $set('line_total', $lineTotal);
                            }),
                            
                        Forms\Components\TextInput::make('tax_rate')
                            ->label('TVA %')
                            ->numeric()
                            ->default(20)
                            ->required()
                            ->minValue(0)
                            ->suffix('%')
                            ->reactive()
                            ->afterStateUpdated(function (Set $set, Get $get) {
                                $quantity = (float)($get('quantity') ?? 0);
                                $unitPrice = (float)($get('unit_price') ?? 0);
                                $discountPercentage = (float)($get('discount_percentage') ?? 0);
                                $taxRate = (float)($get('tax_rate') ?? 0);
                                
                                $basePrice = $quantity * $unitPrice;
                                $discountAmount = $basePrice * ($discountPercentage / 100);
                                $priceAfterDiscount = $basePrice - $discountAmount;
                                $taxAmount = $priceAfterDiscount * ($taxRate / 100);
                                $lineTotal = $priceAfterDiscount + $taxAmount;
                                
                                $set('line_total', $lineTotal);
                            }),
                    ]),
                    
                Forms\Components\TextInput::make('line_total')
                    ->label('Total TTC')
                    ->numeric()
                    ->prefix(config('app.currency_symbol', '€'))
                    ->disabled()
                    ->dehydrated(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Produit')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Quantité')
                    ->numeric()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('transactionUnit.name_with_symbol')
                    ->label('Unité')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('unit_price')
                    ->label('Prix unitaire HT')
                    ->money(config('app.currency', 'eur'))
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('discount_percentage')
                    ->label('Remise')
                    ->numeric()
                    ->suffix('%')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('tax_rate')
                    ->label('TVA')
                    ->numeric()
                    ->suffix('%')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('line_total')
                    ->label('Total TTC')
                    ->money(config('app.currency', 'eur'))
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
