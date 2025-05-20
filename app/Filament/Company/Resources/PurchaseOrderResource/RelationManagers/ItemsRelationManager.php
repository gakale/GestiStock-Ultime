<?php

declare(strict_types=1);

namespace App\Filament\Company\Resources\PurchaseOrderResource\RelationManagers;

use App\Models\Product;
use App\Models\UnitOfMeasure;
use App\Services\UnitConversionService; // Importer le service
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
use Filament\Forms\Components\Placeholder;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Facades\Log;

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
                    ->afterStateUpdated(function (Set $set, ?string $state, UnitConversionService $conversionService) {
                        if ($state) {
                            $product = Product::with(['purchaseUnit', 'basePurchasePriceUnit'])->find($state);
                            if ($product) {
                                // Pré-remplir les champs avec les données du produit
                                $set('product_name', $product->name);
                                $set('product_sku', $product->sku);
                                
                                // Pré-remplir l'unité de transaction avec l'unité d'achat par défaut du produit
                                $transactionUnitId = $product->purchase_unit_id ?? $product->stock_unit_id;
                                $set('transaction_unit_id', $transactionUnitId);

                                // Pré-remplir le prix unitaire basé sur la conversion
                                if ($transactionUnitId && $product->basePurchasePriceUnit && $product->purchase_price > 0) {
                                    $transactionUnit = UnitOfMeasure::find($transactionUnitId);
                                    try {
                                        // Le prix du produit est dans $product->basePurchasePriceUnit
                                        // Nous voulons le prix pour $transactionUnit
                                        // Facteur pour convertir 1 transactionUnit en basePurchasePriceUnit
                                        // Exemple: Achat en Carton (transactionUnit), prix base en Pièce (basePurchasePriceUnit)
                                        // 1 Carton = 12 Pièces. Facteur = 12.
                                        // Prix Carton = Prix Pièce * 12.
                                        $factor = $conversionService->convert(1, $transactionUnit, $product->basePurchasePriceUnit, $product);
                                        $set('unit_price', round((float)$product->purchase_price * $factor, 2)); // Ajuster précision si besoin
                                    } catch (\InvalidArgumentException $e) {
                                        Log::error("Erreur conversion prix (achat) pour produit {$product->id}: " . $e->getMessage());
                                        $set('unit_price', $product->purchase_price); // Fallback au prix de base sans conversion
                                    }
                                } else {
                                    $set('unit_price', $product->purchase_price); // Fallback si pas d'unités pour conversion
                                }
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
                    ->options(UnitOfMeasure::where('is_active', true)->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function (Set $set, Get $get, ?string $state, UnitConversionService $conversionService) {
                        $productId = $get('product_id');
                        if ($productId && $state) {
                            $product = Product::with('basePurchasePriceUnit')->find($productId);
                            $transactionUnit = UnitOfMeasure::find($state);
                            if ($product && $transactionUnit && $product->basePurchasePriceUnit && $product->purchase_price > 0) {
                                try {
                                    $factor = $conversionService->convert(1, $transactionUnit, $product->basePurchasePriceUnit, $product);
                                    $set('unit_price', round((float)$product->purchase_price * $factor, 2));
                                } catch (\InvalidArgumentException $e) {
                                    Log::error("Erreur conversion prix (achat) pour produit {$product->id} après changement d'unité: " . $e->getMessage());
                                    $set('unit_price', $product->purchase_price); // Fallback
                                }
                            } elseif ($product) {
                                $set('unit_price', $product->purchase_price); // Fallback
                            }
                        }
                    }),
                
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
