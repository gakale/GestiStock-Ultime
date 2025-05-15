<?php

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\ProductCategoryResource\Pages;
// use App\Filament\Company\Resources\ProductCategoryResource\RelationManagers;
use App\Models\ProductCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope; // Si vous décidez d'utiliser SoftDeletes plus tard
use Illuminate\Support\Str;

// Form Components
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;

// Table Columns
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;

class ProductCategoryResource extends Resource
{
    protected static ?string $model = ProductCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Catalogue';

    protected static ?int $navigationSort = 2; // Après Produits

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        TextInput::make('name')
                            ->label('Nom de la catégorie')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('slug', Str::slug($state))),
                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->unique(ProductCategory::class, 'slug', ignoreRecord: true)
                            ->maxLength(255),
                        Select::make('parent_id')
                            ->label('Catégorie Parente')
                            ->relationship('parent', 'name', function (Builder $query, Forms\Get $get) {
                                // Empêcher de se sélectionner soi-même ou un de ses enfants comme parent
                                $currentId = $get('id'); // Ne fonctionne pas à la création, seulement à l'édition
                                if ($currentId) { // Si on édite une catégorie existante
                                    // Exclure la catégorie actuelle et ses descendantes (pour éviter les boucles)
                                    // Cette logique peut devenir complexe, pour l'instant, on exclut juste la catégorie actuelle.
                                    // Une solution plus robuste nécessiterait de récupérer tous les IDs des enfants.
                                    return $query->where('id', '!=', $currentId);
                                }
                                return $query;
                            })
                            ->searchable()
                            ->preload()
                            ->placeholder('Aucune catégorie parente'),
                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
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
                TextColumn::make('parent.name') // Afficher le nom de la catégorie parente
                    ->label('Parente')
                    ->placeholder('N/A')
                    ->sortable(),
                TextColumn::make('products_count')->counts('products') // Compter les produits dans la catégorie
                    ->label('Nb. Produits')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Créée le')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductCategories::route('/'),
            'create' => Pages\CreateProductCategory::route('/create'),
            'edit' => Pages\EditProductCategory::route('/{record}/edit'),
        ];
    }
}
