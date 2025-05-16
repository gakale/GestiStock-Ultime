<?php

namespace App\Filament\Company\Widgets;

use App\Models\Invoice; // Assurez-vous que le namespace est correct
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB; // Pour les requêtes agrégées

class MonthlySalesChart extends ChartWidget
{
    protected static ?string $heading = 'Ventes Mensuelles (12 derniers mois)';

    protected static ?string $description = 'Montant total des factures émises par mois.';

    protected static ?string $pollingInterval = null; // Désactiver le polling si les données ne changent pas fréquemment

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 3; // Pour le positionner après les autres widgets

    // Vous pouvez choisir 'line', 'bar', 'pie', 'doughnut', 'radar', 'polarArea'
    public function getType(): string
    {
        return 'bar'; // 'bar' ou 'line' sont de bons choix ici
    }

    protected function getData(): array
    {
        $data = [];
        $labels = [];

        // Période : 12 derniers mois complets incluant le mois actuel
        for ($i = 11; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $labels[] = $month->isoFormat('MMM YYYY'); // Formatage du label (ex: Jan 2024)

            $revenueForMonth = Invoice::query()
                ->whereYear('invoice_date', $month->year)
                ->whereMonth('invoice_date', $month->month)
                // Quels statuts de facture considérer pour les ventes ?
                // 'paid', 'issued', 'partially_paid' sont de bons candidats
                ->whereIn('status', ['paid', 'issued', 'partially_paid'])
                ->sum('total_amount');

            $data[] = round($revenueForMonth, 2);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Ventes Mensuelles',
                    'data' => $data,
                    'backgroundColor' => 'rgba(54, 162, 235, 0.5)', // Bleu clair
                    'borderColor' => 'rgb(54, 162, 235)',
                    'borderWidth' => 1,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array // Optionnel: pour personnaliser les options Chart.js
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'callback' => 'function(value) { return value.toLocaleString("fr-FR", {style:"currency", currency:"EUR"}); }',
                    ],
                ],
            ],
            'plugins' => [
                'tooltip' => [
                    'callbacks' => [
                        'label' => 'function(context) { let label = context.dataset.label || ""; if (label) { label += ": "; } if (context.parsed.y !== null) { label += context.parsed.y.toLocaleString("fr-FR", {style:"currency", currency:"EUR"}); } return label; }',
                    ],
                ],
            ],
        ];
    }
}