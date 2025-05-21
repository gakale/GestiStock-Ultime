<?php

declare(strict_types=1);

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\WarehouseResource\Pages;
use App\Filament\Company\Resources\WarehouseResource\RelationManagers;
use App\Models\Warehouse;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Hidden;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Illuminate\Support\Facades\DB;

class WarehouseResource extends Resource
{
    protected static ?string $model = Warehouse::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2'; 

    protected static ?string $navigationGroup = 'Stocks'; 

    protected static ?string $label = 'Entrepôt';
    protected static ?string $pluralLabel = 'Entrepôts';
    protected static ?string $slug = 'warehouses';

    protected static ?int $navigationSort = 10; 

    public static function form(Form $form): Form
    {
        // Déterminer l'ID du tenant pour le champ caché
        $tenantId = null;
        
        // Méthode 1: Utiliser la fonction tenant() si disponible
        if (function_exists('tenant') && tenant()) {
            $tenantId = tenant()->getTenantKey();
        } else {
            // Méthode 2: Extraire du nom de la base de données
            try {
                $currentDb = DB::connection()->getDatabaseName();
                if (str_starts_with($currentDb, 'tenant_')) {
                    $tenantId = str_replace('tenant_', '', $currentDb);
                }
            } catch (\Exception $e) {
                \Log::error("WarehouseResource: Impossible de déterminer le tenant_id: " . $e->getMessage());
            }
        }
        
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Nom de l\'entrepôt')
                    ->required()
                    ->maxLength(255)
                    ->unique(Warehouse::class, 'name', ignoreRecord: true)
                    ->columnSpanFull(),
                Textarea::make('address')
                    ->label('Adresse (Optionnel)')
                    ->rows(3)
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Actif')
                    ->default(true),
                
                // Champ caché pour le tenant_id
                Hidden::make('tenant_id')
                    ->default($tenantId),
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
                TextColumn::make('address')
                    ->label('Adresse')
                    ->limit(50)
                    ->tooltip(fn (?Warehouse $record) => $record?->address)
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean(),
                TextColumn::make('locations_count')->counts('locations')->label('Nb. Emplacements'), 
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y H:i')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
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
            RelationManagers\LocationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWarehouses::route('/'),
            'create' => Pages\CreateWarehouse::route('/create'),
            'view' => Pages\ViewWarehouse::route('/{record}'),
            'edit' => Pages\EditWarehouse::route('/{record}/edit'),
        ];
    }
}
