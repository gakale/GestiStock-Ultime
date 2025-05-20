<?php

declare(strict_types=1);

namespace App\Filament\Company\Resources\InvoiceResource\RelationManagers;

use App\Models\Product;
use App\Models\UnitOfMeasure;
use App\Services\UnitConversionService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Facades\Log;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $recordTitleAttribute = 'product_name';
    
    /**
     * Calcule et met à jour le champ stock_unit_quantity
     */
    protected function updateStockUnitQuantity(Set $set, Get $get, Product $product, UnitOfMeasure $transactionUnit, float $quantity): void
    {
        // Si pas d'unité de stock définie, utiliser la même quantité
        if (!$product->stockUnit) {
            $set('stock_unit_quantity', $quantity);
            return;
        }
        
        // Si l'unité de transaction est la même que l'unité de stock, pas de conversion nécessaire
        if ($product->stock_unit_id == $transactionUnit->id) {
            $set('stock_unit_quantity', $quantity);
            return;
        }
        
        try {
            $conversionService = app(UnitConversionService::class);
            $stockUnitQuantity = $conversionService->convert(
                $product,
                $quantity,
                $transactionUnit,
                $product->stockUnit
            );
            
            $set('stock_unit_quantity', $stockUnitQuantity);
            
            Log::info("[InvoiceItem] Conversion d'unité réussie", [
                'product' => $product->name,
                'transaction_quantity' => $quantity,
                'transaction_unit' => $transactionUnit->symbol,
                'stock_unit' => $product->stockUnit->symbol,
                'stock_unit_quantity' => $stockUnitQuantity
            ]);
        } catch (\Exception $e) {
            Log::error("[InvoiceItem] Erreur lors de la conversion d'unité", [
                'error' => $e->getMessage(),
                'product' => $product->name
            ]);
            
            // En cas d'erreur, utiliser la même quantité
            $set('stock_unit_quantity', 0);
        }
    }
    
    /**
     * Calcule le total de la ligne
     */
    protected function calculateLineTotal(Set $set, Get $get): void
    {
        $quantity = (float)($get('quantity') ?? 0);
        $unitPrice = (float)($get('unit_price') ?? 0);
        $discountPercentage = (float)($get('discount_percentage') ?? 0);
        $taxRate = (float)($get('tax_rate') ?? 0);
        
        $basePrice = $quantity * $unitPrice;
        $discountAmount = $basePrice * ($discountPercentage / 100);
        $priceAfterDiscount = $basePrice - $discountAmount;
        $taxAmount = $priceAfterDiscount * ($taxRate / 100);
        $lineTotal = $priceAfterDiscount + $taxAmount;
        
        $set('line_total', round($lineTotal, 2));
    }

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
                                
                                // Récupérer les unités puis transformer avec l'accesseur
                                $units = UnitOfMeasure::whereIn('id', $unitIds)->get();
                                return $units->pluck('name_with_symbol', 'id')->toArray();
                            })
                            ->reactive()
                            ->required()
                            ->afterStateUpdated(function (Set $set, ?string $state, Get $get) {
                                $productId = $get('product_id');
                                $quantity = (float)($get('quantity') ?? 0);
                                
                                if (!$productId || !$state) {
                                    return;
                                }
                                
                                $product = Product::with('stockUnit')->find($productId);
                                $transactionUnit = UnitOfMeasure::find($state);
                                
                                if (!$product || !$transactionUnit) {
                                    return;
                                }
                                
                                // 1. Mettre à jour le prix unitaire en fonction de l'unité sélectionnée
                                try {
                                    // Si l'unité de transaction est différente de l'unité de vente, ajuster le prix
                                    if ($product->sales_unit_id && $product->sales_unit_id != $state) {
                                        $salesUnit = UnitOfMeasure::find($product->sales_unit_id);
                                        
                                        if ($salesUnit) {
                                            $conversionService = app(UnitConversionService::class);
                                            $conversionFactor = $conversionService->getConversionFactor(
                                                $salesUnit,
                                                $transactionUnit,
                                                $product
                                            );
                                            
                                            // Ajuster le prix en fonction du facteur de conversion
                                            $newUnitPrice = (float)($product->selling_price ?? 0) * $conversionFactor;
                                            $set('unit_price', round($newUnitPrice, 2));
                                            
                                            Log::info("[InvoiceItem] Prix ajusté pour unité de transaction", [
                                                'product' => $product->name,
                                                'base_price' => $product->selling_price,
                                                'sales_unit' => $salesUnit->symbol,
                                                'transaction_unit' => $transactionUnit->symbol,
                                                'conversion_factor' => $conversionFactor,
                                                'new_price' => $newUnitPrice
                                            ]);
                                        }
                                    }
                                } catch (\Exception $e) {
                                    Log::error("[InvoiceItem] Erreur lors de la conversion de prix", [
                                        'error' => $e->getMessage(),
                                        'product' => $product->name
                                    ]);
                                }
                                
                                // 2. Calculer et mettre à jour stock_unit_quantity
                                $this->updateStockUnitQuantity($set, $get, $product, $transactionUnit, $quantity);
                                
                                // 3. Recalculer le total de la ligne
                                $this->calculateLineTotal($set, $get);
                            })
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
                                $productId = $get('product_id');
                                $transactionUnitId = $get('transaction_unit_id');
                                $quantity = (float)($get('quantity') ?? 0);
                                
                                // Mettre à jour stock_unit_quantity si le produit et l'unité sont sélectionnés
                                if ($productId && $transactionUnitId) {
                                    $product = Product::with('stockUnit')->find($productId);
                                    $transactionUnit = UnitOfMeasure::find($transactionUnitId);
                                    
                                    if ($product && $transactionUnit) {
                                        $this->updateStockUnitQuantity($set, $get, $product, $transactionUnit, $quantity);
                                    }
                                }
                                
                                // Recalculer le total de la ligne
                                $this->calculateLineTotal($set, $get);
                            }),
                            
                        Forms\Components\TextInput::make('unit_price')
                            ->label('Prix unitaire HT')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->step(0.01)
                            ->prefix(config('app.currency_symbol', '€'))
                            ->reactive()
                            ->afterStateUpdated(function (Set $set, Get $get) {
                                $this->calculateLineTotal($set, $get);
                            }),
                            
                        Forms\Components\TextInput::make('discount_percentage')
                            ->label('Remise %')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->reactive()
                            ->afterStateUpdated(function (Set $set, Get $get) {
                                $this->calculateLineTotal($set, $get);
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
                                $this->calculateLineTotal($set, $get);
                            }),
                    ]),
                    
                Forms\Components\TextInput::make('line_total')
                    ->label('Total TTC')
                    ->numeric()
                    ->prefix(config('app.currency_symbol', '€'))
                    ->disabled()
                    ->dehydrated(),
                    
                // Champ caché pour stocker la quantité convertie en unité de stock
                Forms\Components\Hidden::make('stock_unit_quantity')
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
