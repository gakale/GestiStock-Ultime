<?php

declare(strict_types=1);

namespace App\Filament\Company\Resources\QuotationResource\Pages;

use App\Filament\Company\Resources\QuotationResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewQuotation extends ViewRecord
{
    protected static string $resource = QuotationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
            Actions\Action::make('downloadPdf')
                ->label('Télécharger PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('info')
                ->url(function () {
                    // Utiliser la route nommée
                    return route('direct.quotation.pdf', ['quotationId' => $this->record->id]);
                })
                ->openUrlInNewTab(),
        ];
    }
}
