<?php

declare(strict_types=1);

namespace App\Filament\Company\Resources\LocationResource\RelationManagers;

use App\Filament\Company\Resources\LocationResource;
use App\Models\Location;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ChildLocationsRelationManager extends RelationManager
{
    protected static string $relationship = 'childLocations';
    
    protected static ?string $recordTitleAttribute = 'name';
    
    protected static ?string $title = 'Sous-emplacements';
    
    protected static ?string $label = 'sous-emplacement';
    
    protected static ?string $pluralLabel = 'sous-emplacements';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(2)->schema([
                    TextInput::make('name')
                        ->label('Nom du sous-emplacement')
                        ->required()
                        ->maxLength(255)
                        ->unique(table: Location::class, column: 'name', ignoreRecord: true),
                    
                    Select::make('location_type')
                        ->label('Type d\'emplacement')
                        ->options(LocationResource::getLocationTypeOptions())
                        ->required(),
                ]),
                
                TextInput::make('barcode')
                    ->label('Code-barres')
                    ->maxLength(255)
                    ->unique(table: Location::class, column: 'barcode', ignoreRecord: true),
                
                Textarea::make('description')
                    ->label('Description')
                    ->rows(3)
                    ->columnSpanFull(),
                
                Toggle::make('is_active')
                    ->label('Actif')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('location_type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state): string => LocationResource::formatLocationType($state))
                    ->badge()
                    ->color(fn (string $state): string => LocationResource::getLocationTypeColors()[$state] ?? 'gray')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('barcode')
                    ->label('Code-barres')
                    ->searchable()
                    ->toggleable(),
                
                IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean(),
                
                TextColumn::make('child_locations_count')
                    ->label('Sous-emplacements')
                    ->counts('childLocations')
                    ->sortable(),
                
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\SelectFilter::make('location_type')
                    ->label('Type d\'emplacement')
                    ->options(LocationResource::getLocationTypeOptions()),
                
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Actif')
                    ->placeholder('Tous les statuts')
                    ->trueLabel('Sous-emplacements actifs')
                    ->falseLabel('Sous-emplacements inactifs'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Nouveau sous-emplacement')
                    ->mutateFormDataUsing(function (array $data): array {
                        // Préremplit automatiquement l'entrepôt et le parent
                        $parentLocation = $this->getOwnerRecord();
                        $data['warehouse_id'] = $parentLocation->warehouse_id;
                        $data['parent_id'] = $parentLocation->id;
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
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
