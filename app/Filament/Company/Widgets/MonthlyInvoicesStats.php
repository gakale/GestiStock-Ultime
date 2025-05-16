<?php

namespace App\Filament\Company\Widgets;

use App\Models\Invoice; // Assurez-vous que le namespace est correct
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MonthlyInvoicesStats extends BaseWidget
{
    protected static ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $count = Invoice::whereBetween('invoice_date', [$startOfMonth, $endOfMonth])
                        // ->whereIn('status', ['paid', 'issued', 'partially_paid']) // Optionnel: filtrer par statut
                        ->count();

        return [
            Stat::make('Factures ce Mois-ci', $count)
                ->description('Nombre de factures créées ce mois')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('primary'),
        ];
    }
}