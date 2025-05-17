<?php

namespace App\Filament\Company\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Models\ActivityLog;

class RecentActivityLogWidget extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';
    
    protected static ?int $sort = 90;
    
    protected function getTableHeading(): string
    {
        return 'Activités récentes';
    }
    
    public function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return ActivityLog::query()
            ->latest()
            ->limit(10);
    }
    
    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('log_name')
                ->label('Catégorie')
                ->badge()
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    'client_activity' => 'Clients',
                    'product_activity' => 'Produits',
                    'supplier_activity' => 'Fournisseurs',
                    'invoice_activity' => 'Factures',
                    'invoice_item_activity' => 'Lignes de facture',
                    default => $state,
                })
                ->colors([
                    'primary' => 'default',
                    'success' => fn ($state): bool => $state === 'client_activity',
                    'warning' => fn ($state): bool => $state === 'product_activity',
                    'danger' => fn ($state): bool => $state === 'invoice_activity',
                    'info' => fn ($state): bool => $state === 'supplier_activity',
                    'gray' => fn ($state): bool => $state === 'invoice_item_activity',
                ]),
            TextColumn::make('description')
                ->label('Description')
                ->searchable()
                ->limit(50)
                ->tooltip(function (TextColumn $column): ?string {
                    $state = $column->getState();
                    
                    if (strlen($state) <= 50) {
                        return null;
                    }
                    
                    return $state;
                }),
            TextColumn::make('causer.name')
                ->label('Utilisateur')
                ->default('Système'),
            TextColumn::make('created_at')
                ->label('Date')
                ->dateTime('d/m/Y H:i')
                ->sortable(),
        ];
    }
    
    protected function isTablePaginationEnabled(): bool
    {
        return false;
    }
    
    protected function getTableActions(): array
    {
        return [];
    }
    
    protected function getTableBulkActions(): array
    {
        return [];
    }
    
    protected function getTableEmptyStateIcon(): ?string
    {
        return 'heroicon-o-clipboard-document-list';
    }
    
    protected function getTableEmptyStateHeading(): ?string
    {
        return 'Aucune activité enregistrée';
    }
}
