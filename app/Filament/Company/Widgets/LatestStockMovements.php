<?php

namespace App\Filament\Company\Widgets;

use App\Models\StockMovement; // Assurez-vous que le namespace est correct
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextColumn;

class LatestStockMovements extends BaseWidget
{
    protected static ?string $heading = 'Derniers Mouvements de Stock';

    protected int | string | array $columnSpan = 'full'; // Pour qu'il prenne toute la largeur

    protected static ?int $sort = 2; // Pour le positionner après les Stat Widgets (si leur sort est < 2)

    protected static ?string $pollingInterval = '30s'; // Optionnel: rafraîchir toutes les 30 secondes

    public function table(Table $table): Table
    {
        return $table
            ->query(
                StockMovement::query()
                    ->with(['product:id,name', 'user:id,name']) // Eager load product (just id and name) and user
                    ->latest('movement_date') // Trier par la date de mouvement la plus récente
                    ->latest('created_at')    // Puis par date de création pour les mouvements à la même date/heure
                    ->limit(10)               // Afficher les 10 derniers
            )
            ->columns([
                TextColumn::make('movement_date')
                    ->label('Date Mouvement')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('product.name')
                    ->label('Produit')
                    ->searchable()
                    ->sortable(),
                    // Suppression du lien URL pour éviter les problèmes de route
                    // ->url(fn (StockMovement $record): ?string => $record->product ? route('filament.company.resources.products.edit', ['record' => $record->product_id]) : null)

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'purchase_receipt' => 'Réception Achat',
                        'sale_delivery' => 'Livraison Vente',
                        'inventory_adjustment' => 'Ajustement Inventaire',
                        'customer_return' => 'Retour Client',
                        'supplier_return' => 'Retour Fournisseur',
                        'stock_transfer_out' => 'Transfert Sortant',
                        'stock_transfer_in' => 'Transfert Entrant',
                        'production_output' => 'Sortie Production',
                        'production_input' => 'Entrée Production',
                        'damage_loss' => 'Perte/Casse',
                        'initial_stock' => 'Stock Initial',
                        'supplier_return_cancellation' => 'Annul. Retour Four.',
                        'customer_return_cancellation' => 'Annul. Retour Client',
                        'sale_cancellation' => 'Annul. Vente',
                        default => ucfirst(str_replace('_', ' ', $state)),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'purchase_receipt', 'customer_return', 'stock_transfer_in', 'production_input', 'initial_stock', 'supplier_return_cancellation', 'sale_cancellation' => 'success',
                        'sale_delivery', 'supplier_return', 'stock_transfer_out', 'production_output', 'damage_loss', 'customer_return_cancellation' => 'danger',
                        'inventory_adjustment' => 'warning',
                        default => 'gray',
                    })
                    ->searchable(),

                TextColumn::make('quantity_changed')
                    ->label('Qté Modifiée')
                    ->numeric(decimalPlaces: 2) // Adaptez si vous n'utilisez pas de décimales
                    ->alignRight()
                    ->colors(function (TextColumn $column): array {
                        return [
                            'success' => fn ($state) => $state > 0,
                            'danger' => fn ($state) => $state < 0,
                        ];
                    })
                    ->formatStateUsing(fn (string $state): string => ($state > 0 ? '+' : '') . number_format(floatval($state), 2, ',', ' ')),


                TextColumn::make('new_stock_quantity_after_movement')
                    ->label('Nouveau Stock')
                    ->numeric(decimalPlaces: 2) // Adaptez
                    ->alignRight()
                    ->formatStateUsing(fn (string $state): string => number_format(floatval($state), 2, ',', ' ')),


                TextColumn::make('user.name') // Assurez-vous que TenantUser a 'name'
                    ->label('Opérateur')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('reason')
                    ->label('Raison/Note')
                    ->limit(30)
                    ->tooltip(fn (StockMovement $record): ?string => $record->reason ?? $record->notes)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('movement_date', 'desc'); // Tri par défaut
            // Vous pouvez ajouter des actions si nécessaire, mais pour un widget de dashboard, ce n'est souvent pas le cas.
            // ->actions([
            //     Tables\Actions\ViewAction::make()
            //          ->url(fn (StockMovement $record): string => route(config('filament.path').'.app.resources.stock-movements.view', $record)), // Adaptez à votre route de vue si elle existe
            // ])
    }
}