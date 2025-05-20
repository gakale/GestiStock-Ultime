<?php

namespace App\Observers;

use App\Models\PaymentReceived;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PaymentReceivedObserver
{
    /**
     * Handle the PaymentReceived "created" event.
     */
    public function created(PaymentReceived $paymentReceived): void
    {
        $this->updateInvoiceStatusAndAmountPaid($paymentReceived);
    }

    /**
     * Handle the PaymentReceived "updated" event.
     * Si le montant ou la facture associée change.
     */
    public function updated(PaymentReceived $paymentReceived): void
    {
        // Si la facture liée a changé, il faut ajuster l'ancienne et la nouvelle.
        if ($paymentReceived->isDirty('invoice_id')) {
            $oldInvoiceId = $paymentReceived->getOriginal('invoice_id');
            if ($oldInvoiceId) {
                $oldInvoice = Invoice::find($oldInvoiceId);
                if ($oldInvoice) {
                    // Recalculer pour l'ancienne facture (en soustrayant ce paiement)
                    $this->recalculateInvoicePayments($oldInvoice);
                }
            }
        }
        // Mettre à jour la (nouvelle) facture liée
        $this->updateInvoiceStatusAndAmountPaid($paymentReceived);
    }

    /**
     * Handle the PaymentReceived "deleted" event.
     */
    public function deleted(PaymentReceived $paymentReceived): void
    {
        // Retirer le montant de la facture associée
         if ($paymentReceived->invoice_id) {
            $invoice = $paymentReceived->invoice; // Utiliser la relation chargée
            if ($invoice) {
               $this->recalculateInvoicePayments($invoice);
            }
        }
    }

    protected function updateInvoiceStatusAndAmountPaid(PaymentReceived $paymentReceived): void
    {
        if ($paymentReceived->invoice_id) {
            $invoice = Invoice::find($paymentReceived->invoice_id); // Recharger pour s'assurer des données à jour
            if ($invoice) {
                $this->recalculateInvoicePayments($invoice);
            }
        }
    }

    protected function recalculateInvoicePayments(Invoice $invoice): void
    {
        // Somme de tous les paiements reçus pour cette facture
        $totalPaidForInvoice = PaymentReceived::where('invoice_id', $invoice->id)->sum('amount');
        $invoice->amount_paid = $totalPaidForInvoice;

        // Activer la journalisation pour le débogage
        Log::info("[PaymentReceivedObserver] Recalculating Invoice {$invoice->invoice_number}: Total Amount: {$invoice->total_amount}, Amount Paid: {$totalPaidForInvoice}");

        if ($invoice->amount_paid >= $invoice->total_amount) {
            $invoice->status = 'paid';
            Log::info("[PaymentReceivedObserver] Invoice {$invoice->invoice_number} marked as PAID");
        } elseif ($invoice->amount_paid > 0) {
            $invoice->status = 'partially_paid';
            Log::info("[PaymentReceivedObserver] Invoice {$invoice->invoice_number} marked as PARTIALLY PAID");
        } else { // amount_paid est 0 ou null
            // Revenir à un statut précédent si ce n'est pas draft, voided, cancelled
            if (!in_array($invoice->getOriginal('status'), ['draft', 'voided', 'cancelled', 'sent', 'overdue'])) {
                 // Si le paiement annulé était le seul, la facture redevient "sent" ou "overdue"
                if (Carbon::parse($invoice->due_date)->isPast() && $invoice->status !== 'draft') {
                    $invoice->status = 'overdue';
                    Log::info("[PaymentReceivedObserver] Invoice {$invoice->invoice_number} marked as OVERDUE (past due date)");
                } else if ($invoice->status !== 'draft') {
                    $invoice->status = 'sent';
                    Log::info("[PaymentReceivedObserver] Invoice {$invoice->invoice_number} marked as SENT");
                }
            } else if ($invoice->getOriginal('status') === 'paid' || $invoice->getOriginal('status') === 'partially_paid') {
                // Cas où on annule le paiement qui l'avait fait passer à paid/partially_paid
                 if (Carbon::parse($invoice->due_date)->isPast()) {
                    $invoice->status = 'overdue';
                    Log::info("[PaymentReceivedObserver] Invoice {$invoice->invoice_number} marked as OVERDUE (payment cancelled)");
                } else {
                    $invoice->status = 'sent';
                    Log::info("[PaymentReceivedObserver] Invoice {$invoice->invoice_number} marked as SENT (payment cancelled)");
                }
            }
            // Si la facture était 'draft', 'voided', 'cancelled', son statut de paiement ne la change pas.
            // On ne change pas non plus une facture 'sent' en 'overdue' juste parce qu'on annule un paiement
            // sauf si c'est le dernier paiement et que l'échéance est passée.
        }

        $invoice->save();
        Log::info("[PaymentReceivedObserver] Invoice {$invoice->invoice_number} saved with status: {$invoice->status} and amount_paid: {$invoice->amount_paid}");
    }
}