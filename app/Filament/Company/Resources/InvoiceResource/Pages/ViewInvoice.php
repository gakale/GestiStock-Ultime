<?php

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
                    // Utiliser la route directe sans passer par le tenant
                    $protocol = request()->secure() ? 'https://' : 'http://';
                    $host = request()->getHost();
                    $port = ':8000'; // Ajouter le port pour le développement local
                    
                    // Route avec paramètre pour générer le PDF de la facture
                    return $protocol . $host . $port . '/direct-pdf/' . $this->record->id;
                })
                ->openUrlInNewTab() // Ouvrir dans un nouvel onglet pour ne pas quitter la page Filament
        ];
    }
}
