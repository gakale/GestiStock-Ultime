<?php

declare(strict_types=1);

namespace App\Filament\Company\Resources\CreditNoteResource\RelationManagers;

use App\Models\Product;
use App\Models\UnitOfMeasure;
use App\Models\CreditNote;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
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
                                    if ($product) {
                                        $set('product_name', $product->name);
                                        $set('product_sku', $product->sku);
                                        $set('unit_price', $product->selling_price); // Ou prix d'achat si pertinent pour un retour
                                        $set('tax_rate', $product->tax_rate);
                                        
                                        // Définir l'unité de transaction par défaut
                                        // Priorité: Unité de vente, puis Unité de stock, sinon null
                                        if ($product->sales_unit_id) {
                                            $set('transaction_unit_id', $product->sales_unit_id);
                                        } elseif ($product->stock_unit_id) {
                                            $set('transaction_unit_id', $product->stock_unit_id);
                                        } else {
                                            $set('transaction_unit_id', null); // Aucune unité par défaut claire
                                        }
                                        
                                        // Recalculer le total de la ligne si la quantité existe déjà
                                        if ($get('quantity')) {
                                            // Calculer stock_unit_quantity si les unités sont différentes
                                            $this->calculateStockUnitQuantity($set, $get, $product);
                                            // Recalculer le total
                                            $this->calculateLineTotal($set, $get);
                                        }
                                    }
                                } else {
                                    $set('product_name', null);
                                    $set('product_sku', null);
                                    $set('unit_price', 0);
                                    $set('tax_rate', 0); // Réinitialiser aussi la TVA
                                    $set('transaction_unit_id', null);
                                    $set('quantity', 0); // Réinitialiser la quantité
                                    $set('line_total', 0); // Réinitialiser le total
                                }
                                // Forcer le recalcul du total de la ligne
                                $this->calculateLineTotal($set, $get);
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
                            ->afterStateUpdated(function (Set $set, ?string $state, Get $get) {
                                // Recalculer stock_unit_quantity si l'unité change
                                if ($get('product_id')) {
                                    $product = Product::find($get('product_id'));
                                    if ($product) {
                                        $this->calculateStockUnitQuantity($set, $get, $product);
                                    }
                                }
                            })
                            ->columnSpan(1),
                    ]),
                
                Forms\Components\TextInput::make('product_name')
                    ->label('Nom du produit')
                    ->required()
                    ->columnSpan(2),
                    
                Forms\Components\TextInput::make('product_sku')
                    ->label('Référence')
                    ->columnSpan(1),
                
                Forms\Components\Textarea::make('description')
                    ->label('Description')
                    ->rows(2)
                    ->columnSpanFull(),
                
                Forms\Components\Grid::make(4)
                    ->schema([
                        Forms\Components\TextInput::make('quantity')
                            ->label('Quantité')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function (Set $set, Get $get) {
                                // Calculer stock_unit_quantity si un produit est sélectionné
                                if ($get('product_id')) {
                                    $product = Product::find($get('product_id'));
                                    if ($product) {
                                        $this->calculateStockUnitQuantity($set, $get, $product);
                                    }
                                }
                                $this->calculateLineTotal($set, $get);
                            }),
                            
                        Forms\Components\Hidden::make('stock_unit_quantity')
                            ->default(0),
                            
                        Forms\Components\TextInput::make('unit_price')
                            ->label('Prix unitaire HT')
                            ->numeric()
                            ->required()
                            ->prefix(config('app.currency_symbol', '€'))
                            ->reactive()
                            ->afterStateUpdated(function (Set $set, Get $get) {
                                $this->calculateLineTotal($set, $get);
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
            ]);
    }

    private function calculateLineTotal(Set $set, Get $get): void
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
        
        $set('line_total', $lineTotal);
    }
    
    private function calculateStockUnitQuantity(Set $set, Get $get, Product $product): void
    {
        $quantity = (float)($get('quantity') ?? 0);
        $transactionUnitId = $get('transaction_unit_id');
        
        // Si pas d'unité de transaction ou pas d'unité de stock, on utilise la quantité telle quelle
        if (!$transactionUnitId || !$product->stock_unit_id) {
            $set('stock_unit_quantity', $quantity);
            return;
        }
        
        // Si l'unité de transaction est la même que l'unité de stock, pas de conversion nécessaire
        if ($transactionUnitId == $product->stock_unit_id) {
            $set('stock_unit_quantity', $quantity);
            return;
        }
        
        // Sinon, on doit convertir
        $transactionUnit = UnitOfMeasure::find($transactionUnitId);
        $stockUnit = UnitOfMeasure::find($product->stock_unit_id);
        
        if (!$transactionUnit || !$stockUnit) {
            $set('stock_unit_quantity', $quantity); // Fallback si unités non trouvées
            return;
        }
        
        // Conversion entre unités (simplifié, à adapter selon votre logique de conversion)
        // Exemple: si on retourne 2 boîtes de 10 unités, stock_unit_quantity = 20
        $conversionFactor = 1; // Facteur par défaut
        
        // Vérifier si une conversion existe entre ces unités pour ce produit
        // Cette logique dépend de votre implémentation des conversions d'unités
        // Exemple simplifié:
        if ($product->unit_conversions()->where('from_unit_id', $transactionUnitId)->where('to_unit_id', $product->stock_unit_id)->exists()) {
            $conversion = $product->unit_conversions()
                ->where('from_unit_id', $transactionUnitId)
                ->where('to_unit_id', $product->stock_unit_id)
                ->first();
            $conversionFactor = $conversion->conversion_factor;
        } elseif ($transactionUnit->base_unit_id == $stockUnit->id) {
            // Si l'unité de transaction est dérivée de l'unité de stock
            $conversionFactor = $transactionUnit->conversion_factor;
        } elseif ($transactionUnit->id == $stockUnit->base_unit_id) {
            // Si l'unité de stock est dérivée de l'unité de transaction
            $conversionFactor = 1 / $stockUnit->conversion_factor;
        }
        
        $stockUnitQuantity = $quantity * $conversionFactor;
        $set('stock_unit_quantity', $stockUnitQuantity);
    }
    
    // Désactivé car nous utilisons une action personnalisée
    // protected function mutateFormDataBeforeCreate(array $data): array
    // {
    //     // Déplacé vers l'action personnalisée
    //     return $data;
    // }
    
    // Force le recalcul des totaux après la création d'un item
    protected function afterCreate(): void
    {
        $creditNote = CreditNote::find($this->ownerRecord->id);
        if ($creditNote) {
            // Vérifions le nombre d'articles après la création
            $itemCount = $creditNote->items()->count();
            Log::info("[ItemsRelationManager] Nombre d'articles après création: {$itemCount} pour l'avoir #{$creditNote->credit_note_number}");
            
            $creditNote->refresh();
            $creditNote->calculateTotals();
            Log::info("[ItemsRelationManager] Recalcul des totaux après création d'un item pour l'avoir #{$creditNote->credit_note_number}");
        }
    }
    
    // Force le recalcul des totaux après la modification d'un item
    protected function afterSave(): void
    {
        $creditNote = CreditNote::find($this->ownerRecord->id);
        if ($creditNote) {
            $creditNote->refresh();
            $creditNote->calculateTotals();
            Log::info("[ItemsRelationManager] Recalcul des totaux après modification d'un item pour l'avoir #{$creditNote->credit_note_number}");
        }
    }
    
    // Force le recalcul des totaux après la suppression d'un item
    protected function afterDelete(): void
    {
        $creditNote = CreditNote::find($this->ownerRecord->id);
        if ($creditNote) {
            $creditNote->refresh();
            $creditNote->calculateTotals();
            Log::info("[ItemsRelationManager] Recalcul des totaux après suppression d'un item pour l'avoir #{$creditNote->credit_note_number}");
        }
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
                    
                Tables\Columns\TextColumn::make('stock_unit_quantity')
                    ->label('Qté Stock')
                    ->numeric()
                    ->getStateUsing(function ($record) {
                        // Construire la chaîne avec la quantité et le symbole de l'unité de stock du produit
                        if ($record->stock_unit_quantity !== null && $record->product && $record->product->stockUnit) {
                            return $record->stock_unit_quantity . ' ' . $record->product->stockUnit->symbol;
                        }
                        return $record->stock_unit_quantity; // Fallback si pas d'unité de stock ou de produit lié
                    })
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true), // Optionnel: cacher par défaut
                    
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
                Tables\Actions\CreateAction::make()
                    ->using(function (array $data, string $model): Model {
                        // S'assurer que credit_note_id est défini explicitement
                        $data['credit_note_id'] = $this->ownerRecord->id;
                        
                        // Déboguer les données
                        Log::info("[ItemsRelationManager] Création personnalisée d'un article pour l'avoir #{$this->ownerRecord->credit_note_number}", [
                            'credit_note_id' => $data['credit_note_id'],
                            'product_id' => $data['product_id'] ?? null,
                            'quantity' => $data['quantity'] ?? null,
                        ]);
                        
                        // Créer l'article
                        $item = new $model($data);
                        $item->save();
                        
                        // Vérifier que l'article a bien été créé
                        Log::info("[ItemsRelationManager] Article créé avec ID {$item->id}");
                        
                        // Forcer le recalcul des totaux
                        $creditNote = CreditNote::find($this->ownerRecord->id);
                        if ($creditNote) {
                            $creditNote->refresh();
                            $creditNote->calculateTotals();
                        }
                        
                        return $item;
                    })
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
