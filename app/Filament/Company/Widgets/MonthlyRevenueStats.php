<?php

namespace App\Filament\Company\Widgets;

use App\Models\Invoice; // Assurez-vous que le namespace est correct
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MonthlyRevenueStats extends BaseWidget
{
    protected static ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        // Chiffre d'affaires basé sur les factures émises ou payées ce mois-ci
        // Vous pouvez ajuster les statuts selon votre définition du CA
        $revenue = Invoice::whereBetween('invoice_date', [$startOfMonth, $endOfMonth])
                          ->whereIn('status', ['paid', 'issued', 'partially_paid']) // Statuts considérés comme du CA
                          ->sum('total_amount');

        // Formatage du revenu
        $formattedRevenue = number_format($revenue, 2, ',', ' ') . ' €'; // Adaptez la devise

        return [
            Stat::make('CA Facturé ce Mois-ci', $formattedRevenue)
                ->description('Montant total des factures (payées/émises)')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning'),
        ];
    }
}