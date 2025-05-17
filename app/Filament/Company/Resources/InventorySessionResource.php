<?php

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\InventorySessionResource\Pages;
use App\Filament\Company\Resources\InventorySessionResource\RelationManagers;
use App\Models\InventorySession;
use App\Models\Product; // Pour le RelationManager
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\DeleteAction;

class InventorySessionResource extends Resource
{
    protected static ?string $model = InventorySession::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Stocks';

    protected static ?string $recordTitleAttribute = 'reference_number';

    protected static ?int $navigationSort = 2; // Après Produits, avant Mouvements

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Informations de la Session')
                            ->schema([
                                TextInput::make('reference_number')
                                    ->label('Référence')
                                    ->default(fn () => InventorySession::generateNextReferenceNumber())
                                    ->disabled()
                                    ->dehydrated() // S'assure que la valeur est envoyée même si désactivée
                                    ->required(),
                                DateTimePicker::make('inventory_date')
                                    ->label('Date et heure de l\'Inventaire')
                                    ->default(now())
                                    ->seconds(false)
                                    ->required(),
                                Select::make('status')
                                    ->label('Statut')
                                    ->options([
                                        'draft' => 'Brouillon',
                                        'in_progress' => 'Comptage en cours',
                                        'completed' => 'Comptage terminé',
                                        'validated' => 'Validé (Stock MàJ)',
                                        'cancelled' => 'Annulé',
                                    ])
                                    ->default('draft')
                                    ->required()
                                    // Le statut ne devrait pas être modifiable directement ici une fois la session avancée
                                    // On utilisera des actions pour changer de statut.
                                    ->disabled(fn (?InventorySession $record) => $record && $record->status !== 'draft'),
                                // Select::make('user_id') // Normalement auto-assigné
                                //     ->relationship('user', 'name')
                                //     ->default(fn () => auth()->id())
                                //     ->disabled(),
                                Textarea::make('notes')
                                    ->label('Notes Générales')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ])->columns(2),
                    ])->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Statistiques de la Session')
                            ->schema([
                                Forms\Components\Placeholder::make('total_items_placeholder')
                                    ->label('Total Articles Listés')
                                    ->content(fn (?InventorySession $record): string => $record ? $record->total_items : '0'),
                                Forms\Components\Placeholder::make('items_counted_placeholder')
                                    ->label('Articles Comptés')
                                    ->content(fn (?InventorySession $record): string => $record ? $record->items_counted : '0'),
                                // On pourrait ajouter des indicateurs sur les écarts ici après validation
                            ])
                            ->visible(fn (?InventorySession $record) => $record && $record->exists), // Visible seulement en édition
                    ])->columnSpan(['lg' => 1]),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference_number')
                    ->label('Référence')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('inventory_date')
                    ->label('Date Inventaire')
                    ->formatStateUsing(fn ($state) => \Carbon\Carbon::parse($state)->format('d/m/Y H:i'))
                    ->sortable(),
                BadgeColumn::make('status')
                    ->label('Statut')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Brouillon',
                        'in_progress' => 'En Cours',
                        'completed' => 'Terminé',
                        'validated' => 'Validé',
                        'cancelled' => 'Annulé',
                        default => $state,
                    })
                    ->colors([
                        'gray' => 'draft',
                        'warning' => 'in_progress',
                        'info' => 'completed',
                        'success' => 'validated',
                        'danger' => 'cancelled',
                    ])
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Responsable')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('total_items') // Utilise l'accesseur du modèle
                    ->label('Nb. Articles')
                    ->numeric()
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->withCount('items')->orderBy('items_count', $direction); // Tri réel sur le count
                    }),
                TextColumn::make('items_counted') // Utilise l'accesseur du modèle
                    ->label('Nb. Comptés')
                    ->numeric()
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query
                            ->withCount(['items as items_counted_query' => function (Builder $q) {
                                $q->whereNotNull('counted_quantity');
                            }])
                            ->orderBy('items_counted_query', $direction);
                    }),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->formatStateUsing(fn ($state) => \Carbon\Carbon::parse($state)->format('d/m/Y H:i'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Brouillon',
                        'in_progress' => 'Comptage en cours',
                        'completed' => 'Comptage terminé',
                        'validated' => 'Validé',
                        'cancelled' => 'Annulé',
                    ])
            ])
            ->actions([
                ActionGroup::make([ // Groupe d'actions pour une meilleure UI
                    ViewAction::make(),
                    EditAction::make()->visible(fn (InventorySession $record) => in_array($record->status, ['draft', 'in_progress'])), // Édition possible si brouillon ou en cours
                    // Les actions de changement de statut seront sur la page View/Edit
                ])
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (InventorySession $record) => $record->status === 'draft' || $record->status === 'cancelled')
                ]),
            ])
            ->defaultSort('inventory_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemsRelationManager::class,
        ];
    }


    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventorySessions::route('/'),
            'create' => Pages\CreateInventorySession::route('/create'),
            'view' => Pages\ViewInventorySession::route('/{record}'),
            'edit' => Pages\EditInventorySession::route('/{record}/edit'),
        ];
    }
}