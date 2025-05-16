<?php

namespace App\Filament\Company\Widgets;

use App\Models\Product;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn; // Pour un affichage plus visuel de l'alerte

class StockAlerts extends BaseWidget
{
    protected static ?string $heading = 'Alertes de Stock';

    protected int | string | array $columnSpan = 'full'; // Prend toute la largeur

    protected static ?int $sort = 4; // Pour le positionner après le graphique des ventes

    protected static ?string $pollingInterval = '60s'; // Optionnel: rafraîchir toutes les minutes

    // Optionnel : si vous ne voulez que les produits qui ont un seuil défini
    // protected bool $shouldShowIfEmpty = false; 

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Product::query()
                    ->where('is_active', true) // Uniquement les produits actifs
                    // Combine les produits nécessitant un réapprovisionnement OU ceux en dessous du stock minimum
                    ->where(function (Builder $query) {
                        $query->needsReordering() // Utilise le scope défini dans le modèle Product
                              ->orWhere(function (Builder $subQuery) {
                                  $subQuery->belowMinimumStock(); // Utilise le scope défini
                              });
                    })
                    ->with('category:id,name') // Eager load catégorie si vous l'affichez
                    ->orderBy('stock_quantity', 'asc') // Afficher les plus bas en stock en premier
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Produit')
                    ->searchable()
                    ->sortable(),
                    // Désactivation temporaire du lien URL pour éviter les erreurs de route
                    // ->url(fn (Product $record): string => route('filament.company.resources.products.edit', ['record' => $record->id])


                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('stock_quantity')
                    ->label('Stock Actuel')
                    ->numeric(decimalPlaces: 2) // Adaptez
                    ->alignRight()
                    ->sortable(),

                TextColumn::make('stock_reorder_point')
                    ->label('Pt. Réappro.')
                    ->numeric(decimalPlaces: 2) // Adaptez
                    ->alignRight()
                    ->sortable(),

                TextColumn::make('stock_min_threshold')
                    ->label('Stock Min.')
                    ->numeric(decimalPlaces: 2) // Adaptez
                    ->alignRight()
                    ->sortable(),

                BadgeColumn::make('alert_status')
                    ->label('Type d\'Alerte')
                    ->getStateUsing(function (Product $record): string {
                        if ($record->stock_min_threshold !== null && $record->stock_quantity <= $record->stock_min_threshold) {
                            return 'Stock Critique';
                        }
                        if ($record->stock_reorder_point !== null && $record->stock_quantity <= $record->stock_reorder_point) {
                            return 'Réapprovisionner';
                        }
                        return 'N/A'; // Ne devrait pas arriver si la query est correcte
                    })
                    ->colors([
                        'danger' => 'Stock Critique',
                        'warning' => 'Réapprovisionner',
                    ]),
                
                // Optionnel: Quantité à commander pour atteindre le stock optimal (si vous avez stock_max_threshold ou un autre objectif)
                // TextColumn::make('quantity_to_order')
                //     ->label('Qté à Cmdr (Optimal)')
                //     ->getStateUsing(function (Product $record): ?float {
                //         if ($record->stock_reorder_point !== null && $record->stock_quantity <= $record->stock_reorder_point) {
                //             // Exemple: commander pour atteindre le stock_reorder_point + une marge, ou stock_max_threshold si défini
                //             $targetStock = $record->stock_max_threshold ?? ($record->stock_reorder_point + ($record->stock_reorder_point * 0.5)); // Exemple de cible
                //             return max(0, $targetStock - $record->stock_quantity);
                //         }
                //         return null;
                //     })
                //     ->numeric(decimalPlaces: 2)
                //     ->alignRight(),
            ])
            ->emptyStateHeading('Aucun produit en alerte de stock')
            ->emptyStateDescription('Tous vos niveaux de stock sont bons pour le moment.')
            ->paginated(false); // Pour un widget de dashboard, la pagination n'est souvent pas souhaitée
            // ->defaultPaginationPageOption(5) // Si vous voulez paginer, mettez un petit nombre
    }
}