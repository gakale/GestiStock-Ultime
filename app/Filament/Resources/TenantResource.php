<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TenantResource\Pages;
use App\Filament\Resources\TenantResource\RelationManagers;
use App\Models\Tenant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\IconColumn;
use Filament\Forms\Set;
use Filament\Resources\Pages\Page;


class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Administration';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Nom de l\'entreprise')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true) // Pour mettre à jour le slug en direct
                    ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
                        if ($state) {
                            $set('slug', Str::slug($state));
                        }
                    }),
                TextInput::make('slug')
                    ->label('Slug (pour URL)')
                    ->required()
                    ->unique(Tenant::class, 'slug', ignoreRecord: true)
                    ->maxLength(255),
                Toggle::make('ready')
                    ->label('Prêt à l\'utilisation')
                    ->default(true),
                // Le RelationManager des domaines s'affichera automatiquement sur la page d'édition
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID Tenant')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('name')
                    ->label('Nom de l\'entreprise')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('ready')
                    ->label('Prêt')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                // Afficher le domaine principal
                TextColumn::make('domains_list')
                    ->label('Domaine(s)')
                    ->getStateUsing(function (Tenant $record) {
                        return $record->domains->pluck('domain')->implode(', ');
                    }),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->after(function (Tenant $record) {
                        // Supprimer la base de données du tenant lors de la suppression
                        // Attention: ceci est destructif!
                        try {
                            $record->delete(); // Le modèle Tenant de stancl gère la suppression de la BDD
                        } catch (\Exception $e) {
                            \Log::error("Erreur suppression BDD tenant {$record->id}: " . $e->getMessage());
                            // Notifier l'utilisateur ou gérer l'erreur
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->after(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $records->each(function (Tenant $record) {
                                try {
                                    $record->delete();
                                } catch (\Exception $e) {
                                    \Log::error("Erreur suppression BDD tenant (bulk) {$record->id}: " . $e->getMessage());
                                }
                            });
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
           RelationManagers\DomainsRelationManager::class,
        ];
    }


    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
