<?php

namespace App\Filament\Resources\TenantResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;

class DomainsRelationManager extends RelationManager
{
    protected static string $relationship = 'domains';

    protected static ?string $recordTitleAttribute = 'domain'; // L'attribut à utiliser pour le titre de l'enregistrement

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('domain')
                    ->label('Domaine')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true) // Doit être unique dans toute la table des domaines
                    ->placeholder('ex: entreprise-a.gestistock.test'),
                // Vous pouvez ajouter un toggle 'is_primary' si vous gérez plusieurs domaines et en voulez un principal
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            // ->recordTitleAttribute('domain') // Déjà défini plus haut
            ->columns([
                TextColumn::make('domain')
                    ->label('Domaine'),
                TextColumn::make('created_at')
                    ->label('Ajouté le')
                    ->dateTime(),
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