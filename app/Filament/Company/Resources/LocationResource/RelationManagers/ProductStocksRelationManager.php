<?php

declare(strict_types=1);

namespace App\Filament\Company\Resources\LocationResource\RelationManagers;

use App\Models\Product;
use App\Models\ProductLocationStock;
use App\Models\UnitOfMeasure;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProductStocksRelationManager extends RelationManager
{
    protected static string $relationship = 'productStocks';
    
    protected static ?string $recordTitleAttribute = 'id';
    
    protected static ?string $title = 'Stock des produits';
    
    protected static ?string $label = 'stock de produit';
    
    protected static ?string $pluralLabel = 'stocks de produits';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(2)->schema([
                    Select::make('product_id')
                        ->label('Produit')
                        ->relationship('product', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->createOptionForm([
                            TextInput::make('name')
                                ->label('Nom du produit')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('sku')
                                ->label('SKU')
                                ->required()
                                ->maxLength(100)
                                ->unique(Product::class, 'sku'),
                            Select::make('stock_unit_id')
                                ->label('Unité de stock')
                                ->relationship('stockUnit', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),
                        ])
                        ->columnSpanFull(),
                    
                    TextInput::make('quantity')
                        ->label('Quantité')
                        ->numeric()
                        ->minValue(0)
                        ->required()
                        ->default(0),
                    
                    Placeholder::make('unit_placeholder')
                        ->label('Unité')
                        ->content(function (callable $get) {
                            $productId = $get('product_id');
                            if (!$productId) return 'Sélectionnez un produit';
                            
                            $product = Product::find($productId);
                            if (!$product || !$product->stockUnit) return 'Unité non définie';
                            
                            return $product->stockUnit->name . ' (' . $product->stockUnit->symbol . ')';
                        }),
                ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('product.name')
                    ->label('Produit')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('product.sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('quantity')
                    ->label('Quantité')
                    ->formatStateUsing(function (ProductLocationStock $record): string {
                        // Conversion explicite en float pour éviter l'erreur de type
                        $quantity = is_numeric($record->quantity) ? (float)$record->quantity : 0;
                        return number_format($quantity, 2) . ' ' . 
                            ($record->product?->stockUnit?->symbol ?? '');
                    })
                    ->sortable(),
                
                TextColumn::make('product.category.name')
                    ->label('Catégorie')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                
                TextColumn::make('updated_at')
                    ->label('Dernière mise à jour')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('product.name')
            ->filters([
                Tables\Filters\SelectFilter::make('product_category_id')
                    ->label('Catégorie')
                    ->relationship('product.category', 'name'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Ajouter un stock')
                    ->mutateFormDataUsing(function (array $data): array {
                        // Préremplit automatiquement l'emplacement
                        $data['location_id'] = $this->getOwnerRecord()->id;
                        return $data;
                    }),
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
