<?php

declare(strict_types=1);

namespace App\Filament\Company\Resources\InvoiceResource\Pages;

use App\Filament\Company\Resources\InvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
            
            // Bouton pour télécharger la facture en PDF
            Actions\Action::make('downloadPdf')
                ->label('Télécharger PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('info')
                ->url(function () {
                    // Utiliser la route nommée
                    return route('direct.invoice.pdf', ['invoiceId' => $this->record->id]);
                    
                    // Ou si vous préférez construire l'URL manuellement :
                    // $protocol = request()->secure() ? 'https://' : 'http://';
                    // $host = request()->getHost();
                    // $port = request()->getPort() && !in_array(request()->getPort(), [80, 443]) ? ':' . request()->getPort() : '';
                    // return $protocol . $host . $port . '/direct-pdf/invoice/' . $this->record->id;
                })
                ->openUrlInNewTab() // Ouvrir dans un nouvel onglet pour ne pas quitter la page Filament
        ];
    }
}
