<?php

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\UnitOfMeasureResource\Pages;
use App\Filament\Company\Resources\UnitOfMeasureResource\RelationManagers;
use App\Models\UnitOfMeasure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Forms\Get; // Pour la logique conditionnelle

class UnitOfMeasureResource extends Resource
{
    protected static ?string $model = UnitOfMeasure::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube-transparent'; // Icône suggérée

    protected static ?string $navigationGroup = 'Paramètres'; // Ou 'Produits', 'Stocks'

    protected static ?string $label = 'Unité de Mesure';
    protected static ?string $pluralLabel = 'Unités de Mesure';
    protected static ?string $slug = 'units-of-measure';

    protected static ?int $navigationSort = 5; // Ajustez selon votre organisation

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Nom de l\'unité')
                    ->required()
                    ->maxLength(255)
                    ->unique(UnitOfMeasure::class, 'name', ignoreRecord: true)
                    ->columnSpan(2),
                TextInput::make('symbol')
                    ->label('Symbole')
                    ->required()
                    ->maxLength(255)
                    ->unique(UnitOfMeasure::class, 'symbol', ignoreRecord: true),
                Select::make('type')
                    ->label('Type d\'unité')
                    ->options([
                        'countable' => 'Comptable (ex: Pièce, Boîte)',
                        'weight' => 'Poids (ex: Kg, Gramme)',
                        'length' => 'Longueur (ex: Mètre, Cm)',
                        'volume' => 'Volume (ex: Litre, Ml)',
                        'time' => 'Temps (ex: Heure, Jour)', // Si applicable
                        'other' => 'Autre',
                    ])
                    ->default('countable')
                    ->required()
                    ->live(), // Rendre le champ réactif pour que les filtres fonctionnent
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->columnSpanFull(),

                Forms\Components\Section::make('Conversion (si ce n\'est pas une unité de base)')
                    ->description('Si cette unité est une décomposition ou un multiple d\'une autre unité (ex: un "Carton" contient des "Pièces"). Laissez vide si c\'est une unité de base comme "Pièce" ou "Mètre".')
                    ->schema([
                        Select::make('base_unit_id')
                            ->label('Unité de Base de Référence')
                            ->options(function (Get $get) {
                                $type = $get('../../type');
                                $currentId = $get('id');
                                
                                $query = UnitOfMeasure::query();
                                
                                // S'assurer que le type n'est pas null avant de l'utiliser dans la requête
                                if ($type) {
                                    $query->where('type', $type);
                                }
                                
                                $query->orderBy('name');
                                    
                                // Si nous sommes en mode édition, exclure l'unité actuelle
                                if ($currentId) {
                                    $query->where('id', '!=', $currentId);
                                }
                                
                                return $query->pluck('name', 'id')->toArray();
                            })
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText('L\'unité principale à laquelle celle-ci se rapporte (ex: "Pièce" pour un "Carton de 12").'),
                        TextInput::make('conversion_factor')
                            ->label('Facteur de Conversion')
                            ->numeric()
                            ->requiredWith('base_unit_id') // Requis si base_unit_id est rempli
                            ->default(1.00000)
                            ->minValue(0.00001) // Éviter zéro ou négatif
                            ->helperText('Combien d\'unités de base y a-t-il dans CETTE unité ? Ex: si cette unité est "Carton de 12" et l\'unité de base est "Pièce", le facteur est 12.')
                            ->visible(fn (Get $get) => !empty($get('base_unit_id'))), // Visible seulement si une unité de base est sélectionnée
                    ])->columns(2)
                      ->collapsible(),
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
                TextColumn::make('symbol')
                    ->label('Symbole')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'countable' => 'Comptable',
                        'weight' => 'Poids',
                        'length' => 'Longueur',
                        'volume' => 'Volume',
                        'time' => 'Temps',
                        'other' => 'Autre',
                        default => $state,
                    })
                    ->badge()
                    ->sortable(),
                TextColumn::make('baseUnit.name') // Affiche le nom de l'unité de base via la relation
                    ->label('Unité de Base')
                    ->placeholder('N/A (Unité de base)')
                    ->sortable(),
                TextColumn::make('conversion_factor')
                    ->label('Facteur Conv.')
                    ->numeric(decimalPlaces: 5)
                    ->alignRight()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'countable' => 'Comptable',
                        'weight' => 'Poids',
                        'length' => 'Longueur',
                        'volume' => 'Volume',
                        'time' => 'Temps',
                        'other' => 'Autre',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Statut Actif')
                    ->trueLabel('Oui')
                    ->falseLabel('Non'),
                Tables\Filters\Filter::make('is_base_unit')
                    ->label('Est une unité de base')
                    ->query(fn (Builder $query): Builder => $query->whereNull('base_unit_id'))
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    // On pourrait ajouter une action pour désactiver/activer en masse
                ]),
            ])
            ->defaultSort('name', 'asc');
    }

    public static function getRelations(): array
    {
        return [
            // RelationManagers\DerivedUnitsRelationManager::class, // Si on veut voir les unités dérivées
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUnitOfMeasures::route('/'),
            'create' => Pages\CreateUnitOfMeasure::route('/create'),
            'edit' => Pages\EditUnitOfMeasure::route('/{record}/edit'),
            'view' => Pages\ViewUnitOfMeasure::route('/{record}'),
        ];
    }
}