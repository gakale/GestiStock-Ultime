<?php

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\ProductResource\Pages;
// use App\Filament\Company\Resources\ProductResource\RelationManagers; // Si vous en avez
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

// Form Components
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select as FormsSelect; // Alias pour éviter conflit si Select est utilisé pour autre chose
use Filament\Forms\Get; // Pour la logique conditionnelle
use Filament\Forms\Set; // Pour la logique conditionnelle

// Table Columns
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ToggleColumn; // Pour modifier directement dans la table

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box'; // Icône pour le menu

    protected static ?string $navigationGroup = 'Catalogue'; // Grouper dans le menu

    protected static ?int $navigationSort = 1; // Ordre dans le groupe

    protected static ?string $recordTitleAttribute = 'name'; // Utilisé pour le titre de la page d'édition, etc.

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Informations Générales')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nom du produit')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true) // Pour mettre à jour le slug en direct
                            ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('slug', Str::slug($state))),
                        TextInput::make('slug')
                            ->label('Slug (pour URL)')
                            ->required()
                            ->unique(Product::class, 'slug', ignoreRecord: true)
                            ->maxLength(255),
                        FormsSelect::make('product_category_id') // Champ pour la catégorie
                            ->label('Catégorie')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([ // Permettre de créer une catégorie à la volée
                                TextInput::make('name')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('slug', Str::slug($state))),
                                TextInput::make('slug')->required()->unique(ProductCategory::class, 'slug'),
                            ])
                            ->placeholder('Sélectionner une catégorie')
                            ->columnSpanFull(), // Ajustez le columnSpan si nécessaire
                        Textarea::make('description')
                            ->label('Description')
                            ->columnSpanFull()
                            ->rows(3),
                    ]),

                Section::make('Tarification et Taxes')
                    ->columns(2)
                    ->schema([
                        TextInput::make('purchase_price')
                            ->label('Prix d\'achat de Base')
                            ->numeric()
                            ->prefix('€')
                            ->minValue(0)
                            ->nullable()
                            ->reactive(), // Pour que l'unité de prix de base puisse réagir
                        FormsSelect::make('base_purchase_price_unit_id')
                            ->label('Unité du Prix d\'Achat de Base')
                            ->relationship('basePurchasePriceUnit', 'name')
                            ->options(UnitOfMeasure::where('is_active', true)->pluck('name', 'id'))
                            ->searchable()->preload()
                            ->helperText("Unité pour laquelle le prix d'achat ci-dessus est défini.")
                            // Pré-remplir dynamiquement ou laisser l'utilisateur choisir
                            ->default(function (Get $get, ?Product $record) {
                                // Si en édition et déjà défini, utiliser la valeur existante
                                if ($record && $record->base_purchase_price_unit_id) return $record->base_purchase_price_unit_id;
                                // Sinon, utiliser l'unité d'achat par défaut du produit ou son unité de stock
                                return $get('purchase_unit_id') ?? $get('stock_unit_id');
                            })
                            ->required(), // Important d'avoir une unité pour le prix

                        TextInput::make('selling_price')
                            ->label('Prix de Vente de Base')
                            ->numeric()
                            ->prefix('€')
                            ->required()
                            ->minValue(0)
                            ->reactive(),
                        FormsSelect::make('base_selling_price_unit_id')
                            ->label('Unité du Prix de Vente de Base')
                            ->relationship('baseSellingPriceUnit', 'name')
                            ->options(UnitOfMeasure::where('is_active', true)->pluck('name', 'id'))
                            ->searchable()->preload()
                            ->helperText("Unité pour laquelle le prix de vente ci-dessus est défini.")
                            ->default(function (Get $get, ?Product $record) {
                                if ($record && $record->base_selling_price_unit_id) return $record->base_selling_price_unit_id;
                                return $get('sales_unit_id') ?? $get('stock_unit_id');
                            })
                            ->required(),
                    ]),
                    
                Section::make('Unités de Mesure')
                    ->columns(3)
                    ->schema([
                        FormsSelect::make('stock_unit_id')
                            ->label('Unité de Stock Principale')
                            ->relationship('stockUnit', 'name')
                            ->options(UnitOfMeasure::where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive() // Pour que les unités de prix de base puissent réagir
                            ->afterStateUpdated(function (Set $set, Get $get, ?string $state) {
                                // Si l'unité de prix de base n'est pas encore définie ou était égale à l'ancienne unité de stock
                                if (empty($get('base_purchase_price_unit_id')) || $get('base_purchase_price_unit_id') === $get('purchase_unit_id')) {
                                    $set('base_purchase_price_unit_id', $get('purchase_unit_id') ?? $state);
                                }
                                if (empty($get('base_selling_price_unit_id')) || $get('base_selling_price_unit_id') === $get('sales_unit_id')) {
                                    $set('base_selling_price_unit_id', $get('sales_unit_id') ?? $state);
                                }
                            })
                            ->helperText('L\'unité dans laquelle le stock est compté et géré.'),
                        FormsSelect::make('purchase_unit_id')
                            ->label('Unité d\'Achat par Défaut')
                            ->relationship('purchaseUnit', 'name')
                            ->options(UnitOfMeasure::where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->reactive()
                            ->afterStateUpdated(function (Set $set, Get $get, ?string $state) {
                                $set('base_purchase_price_unit_id', $state ?? $get('stock_unit_id'));
                            })
                            ->helperText('L\'unité typiquement utilisée pour acheter ce produit.'),
                        FormsSelect::make('sales_unit_id')
                            ->label('Unité de Vente par Défaut')
                            ->relationship('salesUnit', 'name')
                            ->options(UnitOfMeasure::where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->reactive()
                            ->afterStateUpdated(function (Set $set, Get $get, ?string $state) {
                                $set('base_selling_price_unit_id', $state ?? $get('stock_unit_id'));
                            })
                            ->helperText('L\'unité typiquement utilisée pour vendre ce produit.'),
                    ]),
                    
                Section::make('Stock et Seuils')
                    ->columns(2)
                    ->schema([
                        TextInput::make('stock_quantity')
                            ->label('Quantité en Stock Actuelle')
                            ->integer()
                            ->required()
                            ->default(0)
                            ->minValue(0)
                            ->helperText(fn (Forms\Get $get): ?string => 
                                ($unitId = $get('stock_unit_id')) ? 
                                ('En ' . (UnitOfMeasure::find($unitId)?->name ?? 'unités de stock')) : 
                                'Veuillez d\'abord sélectionner une unité de stock.'
                            ),
                        TextInput::make('stock_min_threshold')
                            ->label('Seuil de Stock Minimum')
                            ->numeric()
                            ->helperText('Alerte si le stock passe en dessous.')
                            ->nullable(),
                        TextInput::make('stock_reorder_point')
                            ->label('Point de Réapprovisionnement')
                            ->numeric()
                            ->helperText('Seuil pour déclencher une nouvelle commande.')
                            ->nullable(),
                        TextInput::make('stock_max_threshold')
                            ->label('Seuil de Stock Maximum')
                            ->numeric()
                            ->helperText('Pour éviter le surstockage.')
                            ->nullable(),
                        Toggle::make('is_active')
                            ->label('Produit Actif')
                            ->default(true),
                    ]),

                Section::make('Identification')
                    ->columns(2)
                    ->schema([
                        TextInput::make('sku')
                            ->label('SKU (Code Article)')
                            ->unique(Product::class, 'sku', ignoreRecord: true)
                            ->maxLength(255)
                            ->helperText('Laissez vide pour une génération automatique simple.'),
                        TextInput::make('barcode')
                            ->label('Code-barres (EAN, UPC)')
                            ->unique(Product::class, 'barcode', ignoreRecord: true)
                            ->maxLength(255)
                            ->nullable(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category.name') // Afficher le nom de la catégorie
                    ->label('Catégorie')
                    ->sortable()
                    ->searchable()
                    ->placeholder('N/A'),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true), // Option pour cacher/afficher la colonne
                TextColumn::make('selling_price')
                    ->label('Prix de Vente')
                    ->money('eur') // Ou votre devise
                    ->sortable(),
                TextColumn::make('stock_quantity')
                    ->label('Stock')
                    ->sortable()
                    ->color(fn ($record) => 
                        $record->stock_min_threshold !== null && $record->stock_quantity <= $record->stock_min_threshold ? 'danger' : 
                        ($record->stock_reorder_point !== null && $record->stock_quantity <= $record->stock_reorder_point ? 'warning' : 'success')
                    )
                    ->description(fn (Product $record): ?string => $record->stockUnit?->symbol),
                TextColumn::make('stock_min_threshold')
                    ->label('Stock Min')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('stock_reorder_point')
                    ->label('Pt. Réappro.')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('stock_max_threshold')
                    ->label('Stock Max')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('stockUnit.name')
                    ->label('Unité Stock')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('purchaseUnit.name')
                    ->label('Unité Achat')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('salesUnit.name')
                    ->label('Unité Vente')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('basePurchasePriceUnit.name')
                    ->label('Unité Prix Achat')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('baseSellingPriceUnit.name')
                    ->label('Unité Prix Vente')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean()
                    ->sortable(),
                // ToggleColumn::make('is_active') // Si vous voulez pouvoir changer directement dans la table
                //     ->label('Actif'),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(), // Si vous utilisez SoftDeletes
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(), // Deviendra ForceDelete si SoftDeletes est actif
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(), // Si SoftDeletes
                    Tables\Actions\RestoreBulkAction::make(),   // Si SoftDeletes
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // RelationManagers iront ici plus tard (ex: variantes, images)
        ];
    }
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class, // Nécessaire si vous voulez le TrashedFilter
            ]);
    }
}
