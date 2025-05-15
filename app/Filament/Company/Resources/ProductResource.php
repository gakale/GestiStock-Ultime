<?php

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\ProductResource\Pages;
// use App\Filament\Company\Resources\ProductResource\RelationManagers; // Si vous en avez
use App\Models\Product;
use App\Models\ProductCategory;
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

                Section::make('Tarification et Stock')
                    ->columns(2)
                    ->schema([
                        TextInput::make('purchase_price')
                            ->label('Prix d\'achat')
                            ->numeric()
                            ->prefix('€') // Ou votre devise
                            ->minValue(0)
                            ->nullable(),
                        TextInput::make('selling_price')
                            ->label('Prix de vente')
                            ->numeric()
                            ->prefix('€') // Ou votre devise
                            ->required()
                            ->minValue(0),
                        TextInput::make('stock_quantity')
                            ->label('Quantité en stock')
                            ->integer()
                            ->required()
                            ->default(0)
                            ->minValue(0),
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
                    ->sortable(),
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
