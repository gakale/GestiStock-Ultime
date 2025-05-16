<?php

namespace App\Filament\Company\Widgets;

use App\Models\Product;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TotalActiveProductsStats extends BaseWidget
{
    protected static ?string $pollingInterval = null; // Désactiver le polling si non nécessaire

    protected function getStats(): array
    {
        // Assurez-vous que le modèle Product a bien un scope 'active' ou un champ 'is_active'
        // Si vous avez un champ 'is_active' (boolean) :
        $count = Product::where('is_active', true)->count();
        // Si vous avez un champ 'status' avec une valeur comme 'active':
        // $count = Product::where('status', 'active')->count();

        return [
            Stat::make('Produits Actifs', $count)
                ->description('Nombre total de produits actifs dans le catalogue')
                ->descriptionIcon('heroicon-m-archive-box')
                ->color('success'),
        ];
    }
}