<?php

declare(strict_types=1);

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\LocationResource\Pages;
use App\Filament\Company\Resources\LocationResource\RelationManagers;
use App\Models\Location;
use App\Models\Warehouse;
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
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Forms\Get; // Pour la visibilité conditionnelle
use Illuminate\Support\Facades\DB;

class LocationResource extends Resource
{
    protected static ?string $model = Location::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationGroup = 'Stocks';

    protected static ?string $label = 'Emplacement de Stock';
    protected static ?string $pluralLabel = 'Emplacements de Stock';
    protected static ?string $slug = 'stock-locations';

    protected static ?int $navigationSort = 11;

    public static function form(Form $form): Form
    {
        // Déterminer l'ID du tenant pour le champ caché
        $tenantId = null;
        
        // Méthode 1: Utiliser la fonction tenant() si disponible
        if (function_exists('tenant') && tenant()) {
            $tenantId = tenant()->getTenantKey();
            \Log::info("LocationResource form: tenant_id défini via tenant(): {$tenantId}");
        } 
        // Méthode 2: Extraire du nom de la base de données
        else {
            try {
                $currentDb = DB::connection()->getDatabaseName();
                if (str_starts_with($currentDb, 'tenant_')) {
                    $tenantId = str_replace('tenant_', '', $currentDb);
                    \Log::info("LocationResource form: tenant_id défini depuis le nom de la base de données: {$tenantId}");
                }
            } catch (\Exception $e) {
                \Log::error("LocationResource form: Impossible de déterminer le tenant_id: " . $e->getMessage());
            }
        }
        
        // Vérification finale
        if (empty($tenantId)) {
            \Log::error("LocationResource form: Impossible de déterminer le tenant_id");
        }
        
        return $form
            ->schema([
                Grid::make(2)->schema([
                    Select::make('warehouse_id')
                        ->label('Entrepôt')
                        ->relationship('warehouse', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->reactive(), // Pour filtrer les emplacements parents
                    Select::make('parent_location_id')
                        ->label('Emplacement Parent (Optionnel)')
                        ->options(function (Get $get) {
                            $warehouseId = $get('warehouse_id');
                            if (!$warehouseId) {
                                return [];
                            }
                            // Lister les emplacements du même entrepôt
                            return Location::where('warehouse_id', $warehouseId)
                                           // ->whereNull('parent_location_id') // Si on ne veut que des parents de premier niveau
                                           ->pluck('name', 'id');
                        })
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->helperText('Ex: Si cet emplacement est une "Allée", le parent pourrait être une "Zone".'),
                ]),
                Hidden::make('tenant_id')
                    ->default($tenantId),
                TextInput::make('name')
                    ->label('Nom de l\'emplacement')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Ex: Zone A, Allée 01, Casier B-02-03, Quai de Réception...'),
                TextInput::make('barcode')
                    ->label('Code-barres (Optionnel)')
                    ->unique(Location::class, 'barcode', ignoreRecord: true)
                    ->maxLength(255),
                Select::make('location_type')
                    ->label('Type d\'emplacement (Optionnel)')
                    ->options(self::getLocationTypeOptions())
                    ->searchable(),
                Grid::make(3)->schema([
                    Toggle::make('is_pickable')
                        ->label('Prélevable ?')
                        ->helperText('Peut-on prendre du stock depuis ici ?')
                        ->default(true),
                    Toggle::make('is_storable')
                        ->label('Stockable ?')
                        ->helperText('Peut-on y déposer du stock ?')
                        ->default(true),
                    TextInput::make('sequence')
                        ->label('Séquence/Ordre')
                        ->numeric()
                        ->default(0)
                        ->helperText('Pour trier ou ordonner (ex: ordre de picking).'),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nom Emplacement')->searchable()->sortable(),
                TextColumn::make('fullPath')->label('Chemin Complet')->searchable()->limit(70)->tooltip(fn(Location $record) => $record->fullPath), // Utilise l'accesseur
                TextColumn::make('warehouse.name')->label('Entrepôt')->searchable()->sortable(),
                // TextColumn::make('parentLocation.name')->label('Parent')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('location_type')->label('Type')
                    ->formatStateUsing(fn (?string $state): string => self::formatLocationType($state))
                    ->badge()
                    ->color(fn (?string $state): string => 
                        match(true) {
                            in_array($state, ['aisle', 'shelf', 'rack', 'bin']) => 'info',
                            in_array($state, ['receiving_dock', 'shipping_dock', 'buffer', 'quality_control', 'returns']) => 'warning',
                            in_array($state, ['storage_zone', 'picking_zone']) => 'primary',
                            default => 'gray',
                        }
                    )
                    ->searchable()->sortable()->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_pickable')->label('Prélevable')->boolean(),
                IconColumn::make('is_storable')->label('Stockable')->boolean(),
                TextColumn::make('unique_products_count')
                    ->label('Nb. Produits Différents')
                    ->description(fn (Location $record): string => 
                        'Total: ' . number_format($record->total_stocked_items, 2) . ' articles')
                    ->badge()
                    ->color(fn (Location $record): string => $record->unique_products_count > 0 ? 'success' : 'gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('warehouse_id')->relationship('warehouse', 'name')->label('Entrepôt'),
                Tables\Filters\SelectFilter::make('location_type')->options(self::getLocationTypeOptions())->label('Type d\'emplacement'),
                Tables\Filters\TernaryFilter::make('is_pickable')->label('Prélevable'),
                Tables\Filters\TernaryFilter::make('is_storable')->label('Stockable'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('warehouse.name', 'asc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ChildLocationsRelationManager::class, // Pour gérer les sous-emplacements
            RelationManagers\ProductStocksRelationManager::class, // Pour voir le stock dans cet emplacement
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLocations::route('/'),
            'create' => Pages\CreateLocation::route('/create'),
            'edit' => Pages\EditLocation::route('/{record}/edit'),
            'view' => Pages\ViewLocation::route('/{record}'),
        ];
    }

    /**
     * Options pour les types d'emplacements
     */
    public static function getLocationTypeOptions(): array
    {
        return [
            'receiving_dock' => 'Quai de Réception',
            'shipping_dock' => 'Quai d\'Expédition',
            'storage_zone' => 'Zone de Stockage',
            'picking_zone' => 'Zone de Picking',
            'aisle' => 'Allée',
            'shelf' => 'Étagère',
            'rack' => 'Rayonnage',
            'bin' => 'Casier/Bac',
            'buffer' => 'Zone Tampon',
            'quality_control' => 'Contrôle Qualité',
            'returns' => 'Zone Retours',
            'other' => 'Autre',
        ];
    }

    /**
     * Formater l'affichage du type d'emplacement
     */
    public static function formatLocationType(?string $state): string
    {
        return self::getLocationTypeOptions()[$state] ?? ucfirst(str_replace('_', ' ', $state ?? ''));
    }

    /**
     * Couleurs pour les badges de type d'emplacement
     */
    public static function getLocationTypeColors(): array
    {
        return [
            'info' => fn ($state) => in_array($state, ['aisle', 'shelf', 'rack', 'bin']),
            'warning' => fn ($state) => in_array($state, ['receiving_dock', 'shipping_dock', 'buffer', 'quality_control', 'returns']),
            'primary' => fn ($state) => in_array($state, ['storage_zone', 'picking_zone']),
            'gray' => fn ($state) => $state === 'other' || $state === null,
        ];
    }
}
