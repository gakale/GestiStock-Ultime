<?php

namespace App\Filament\Company\Resources\SupplierPaymentResource\Pages;

use App\Filament\Company\Resources\SupplierPaymentResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use App\Models\SupplierInvoice;

class CreateSupplierPayment extends CreateRecord
{
    protected static string $resource = SupplierPaymentResource::class;

    protected function afterCreate(): void
    {
        $payment = $this->record;
        $invoiceApplications = $this->data['invoice_applications'] ?? [];

        if (empty($invoiceApplications)) {
            return;
        }

        // Vérifier que le montant total appliqué ne dépasse pas le montant du paiement
        $totalAmountToApplyFromForm = collect($invoiceApplications)->sum(function ($app) {
            return (float)($app['amount_applied'] ?? 0);
        });

        if ($totalAmountToApplyFromForm > (float)$payment->amount + 0.001) { // +0.001 pour gérer les erreurs de float
            Notification::make()
                ->title('Erreur d\'Allocation')
                ->body('Le montant total que vous tentez d\'appliquer (' . number_format($totalAmountToApplyFromForm, 2, ',', ' ') . '€) est supérieur au montant du paiement (' . number_format($payment->amount, 2, ',', ' ') . '€). Veuillez corriger.')
                ->danger()
                ->send();
            
            // Ne pas exécuter la transaction si l'allocation est mauvaise à la création
            // Cela signifie que le paiement sera créé mais aucune facture ne sera liée.
            // L'utilisateur devra éditer pour corriger.
            return;
        }

        // Appliquer le paiement aux factures sélectionnées
        DB::transaction(function () use ($payment, $invoiceApplications) {
            foreach ($invoiceApplications as $application) {
                $invoiceId = $application['supplier_invoice_id'] ?? null;
                $amountApplied = (float)($application['amount_applied'] ?? 0);

                if (!$invoiceId || $amountApplied <= 0) {
                    continue;
                }

                $invoice = SupplierInvoice::find($invoiceId);
                if (!$invoice) {
                    continue;
                }

                // Attacher la facture au paiement avec le montant appliqué
                $payment->supplierInvoices()->attach($invoiceId, [
                    'amount_applied' => $amountApplied,
                ]);

                // Mettre à jour le montant payé et le statut de la facture
                $invoice->amount_paid += $amountApplied;
                
                // Mettre à jour le statut de la facture
                if ($invoice->amount_paid >= $invoice->total_amount) {
                    $invoice->status = 'paid';
                } elseif ($invoice->amount_paid > 0) {
                    $invoice->status = 'partially_paid';
                }
                
                $invoice->save();
            }
        });
    }
}
