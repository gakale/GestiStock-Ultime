<?php

namespace App\Filament\Company\Widgets;

use App\Models\Client; // Assurez-vous que le namespace est correct
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TotalClientsStats extends BaseWidget
{
    protected static ?string $pollingInterval = null;

    protected function getStats(): array
    {
        // Si vous avez un champ is_active sur Client
        // $count = Client::where('is_active', true)->count();
        // Sinon, simplement le total
        $count = Client::count();

        return [
            Stat::make('Clients Enregistrés', $count)
                ->description('Nombre total de clients')
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),
        ];
    }
}