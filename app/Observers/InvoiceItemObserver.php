<?php

namespace App\Observers;

use App\Models\InvoiceItem;
use App\Models\SalesOrderItem;
use Illuminate\Support\Facades\Log;

class InvoiceItemObserver
{
    /**
     * Handle the InvoiceItem "created" event.
     */
    public function created(InvoiceItem $invoiceItem): void
    {
        Log::info("[InvoiceItemObserver] CREATED event for InvoiceItem ID: {$invoiceItem->id}. Parent Invoice status: " . $invoiceItem->invoice?->status);
        
        // Si la facture est liée à un sales_order_item (via un sales_order_id sur Invoice, puis lien des items)
        if ($invoiceItem->sales_order_item_id) {
            $soItem = SalesOrderItem::find($invoiceItem->sales_order_item_id);
            if ($soItem) {
                $soItem->increment('quantity_invoiced', $invoiceItem->quantity);
                $soItem->salesOrder?->checkAndUpdateStatus();
                Log::info("[InvoiceItemObserver] Updated SalesOrderItem ID: {$soItem->id}, incremented quantity_invoiced by {$invoiceItem->quantity}");
            }
        }
    }

    /**
     * Handle the InvoiceItem "updated" event.
     */
    public function updated(InvoiceItem $invoiceItem): void
    {
        //
    }

    /**
     * Handle the InvoiceItem "deleted" event.
     */
    public function deleted(InvoiceItem $invoiceItem): void
    {
        Log::info("[InvoiceItemObserver] DELETED event for InvoiceItem ID: {$invoiceItem->id}.");
        
        // Si la facture est liée à un sales_order_item
        if ($invoiceItem->sales_order_item_id) {
            $soItem = SalesOrderItem::find($invoiceItem->sales_order_item_id);
            if ($soItem) {
                $soItem->decrement('quantity_invoiced', $invoiceItem->quantity);
                $soItem->salesOrder?->checkAndUpdateStatus();
                Log::info("[InvoiceItemObserver] Updated SalesOrderItem ID: {$soItem->id}, decremented quantity_invoiced by {$invoiceItem->quantity}");
            }
        }
    }

    /**
     * Handle the InvoiceItem "restored" event.
     */
    public function restored(InvoiceItem $invoiceItem): void
    {
        //
    }

    /**
     * Handle the InvoiceItem "force deleted" event.
     */
    public function forceDeleted(InvoiceItem $invoiceItem): void
    {
        //
    }
}
