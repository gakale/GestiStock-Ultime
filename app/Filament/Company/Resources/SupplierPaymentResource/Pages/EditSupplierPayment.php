<?php

namespace App\Filament\Company\Resources\SupplierPaymentResource\Pages;

use App\Filament\Company\Resources\SupplierPaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use App\Models\SupplierInvoice;

class EditSupplierPayment extends EditRecord
{
    protected static string $resource = SupplierPaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $payment = $this->record;
        $newInvoiceApplications = $this->data['invoice_applications'] ?? [];

        if (empty($newInvoiceApplications)) {
            return;
        }

        // Vérifier que le montant total appliqué ne dépasse pas le montant du paiement
        $totalAmountToApplyFromForm = collect($newInvoiceApplications)->sum(function ($app) {
            return (float)($app['amount_applied'] ?? 0);
        });

        if ($totalAmountToApplyFromForm > (float)$payment->amount + 0.001) { // +0.001 pour gérer les erreurs de float
            Notification::make()
                ->title('Erreur d\'Allocation')
                ->body('Le montant total que vous tentez d\'appliquer (' . number_format($totalAmountToApplyFromForm, 2, ',', ' ') . '€) est supérieur au montant du paiement (' . number_format($payment->amount, 2, ',', ' ') . '€). Veuillez corriger.')
                ->danger()
                ->send();
            
            // Pour EditRecord, on ne fait rien sur les applications pour éviter d'enregistrer un état incohérent.
            // L'utilisateur est notifié et devra corriger et re-sauvegarder.
            // Les anciennes applications (avant cette sauvegarde) restent en place.
            return;
        }

        // Récupérer les applications existantes
        $existingApplications = $payment->supplierInvoices()->get()->keyBy('id')->map(function ($invoice) {
            return [
                'supplier_invoice_id' => $invoice->id,
                'amount_applied' => $invoice->pivot->amount_applied,
            ];
        })->toArray();

        // Organiser les nouvelles applications par ID de facture
        $newApplicationsById = collect($newInvoiceApplications)->keyBy('supplier_invoice_id')->toArray();

        DB::transaction(function () use ($payment, $existingApplications, $newApplicationsById) {
            // 1. Traiter les factures existantes qui sont modifiées ou supprimées
            foreach ($existingApplications as $invoiceId => $existingApp) {
                $invoice = SupplierInvoice::find($invoiceId);
                if (!$invoice) continue;

                // Si la facture n'est plus dans les nouvelles applications, la détacher
                if (!isset($newApplicationsById[$invoiceId])) {
                    // Soustraire le montant appliqué précédemment
                    $invoice->amount_paid -= $existingApp['amount_applied'];
                    
                    // Mettre à jour le statut
                    if ($invoice->amount_paid <= 0) {
                        $invoice->status = 'pending';
                        $invoice->amount_paid = 0; // Pour éviter les valeurs négatives
                    } elseif ($invoice->amount_paid < $invoice->total_amount) {
                        $invoice->status = 'partially_paid';
                    }
                    
                    $invoice->save();
                    $payment->supplierInvoices()->detach($invoiceId);
                }
                // Si le montant appliqué a changé
                elseif (abs($existingApp['amount_applied'] - $newApplicationsById[$invoiceId]['amount_applied']) > 0.001) {
                    $newAmount = $newApplicationsById[$invoiceId]['amount_applied'];
                    $difference = $newAmount - $existingApp['amount_applied'];
                    
                    // Mettre à jour le montant payé de la facture
                    $invoice->amount_paid += $difference;
                    
                    // Mettre à jour le statut
                    if ($invoice->amount_paid >= $invoice->total_amount) {
                        $invoice->status = 'paid';
                    } elseif ($invoice->amount_paid > 0) {
                        $invoice->status = 'partially_paid';
                    } else {
                        $invoice->status = 'pending';
                        $invoice->amount_paid = 0; // Pour éviter les valeurs négatives
                    }
                    
                    $invoice->save();
                    $payment->supplierInvoices()->updateExistingPivot($invoiceId, [
                        'amount_applied' => $newAmount,
                    ]);
                }
            }

            // 2. Traiter les nouvelles factures
            foreach ($newApplicationsById as $invoiceId => $newApp) {
                // Ignorer les factures déjà traitées
                if (isset($existingApplications[$invoiceId])) {
                    continue;
                }

                $invoice = SupplierInvoice::find($invoiceId);
                if (!$invoice) continue;

                $amountApplied = (float)$newApp['amount_applied'];
                if ($amountApplied <= 0) continue;

                // Attacher la nouvelle facture
                $payment->supplierInvoices()->attach($invoiceId, [
                    'amount_applied' => $amountApplied,
                ]);

                // Mettre à jour le montant payé et le statut
                $invoice->amount_paid += $amountApplied;
                
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
