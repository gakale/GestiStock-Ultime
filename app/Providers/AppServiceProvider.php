<?php

namespace App\Providers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\GoodsReceiptItem;
use App\Models\Invoice;
use App\Models\DeliveryNoteItem;
use App\Models\DeliveryNote;
use App\Observers\PurchaseOrderObserver;
use App\Observers\PurchaseOrderItemObserver;
use App\Observers\GoodsReceiptItemObserver;
use App\Observers\InvoiceObserver;
use App\Observers\DeliveryNoteItemObserver;
use App\Observers\DeliveryNoteObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Enregistrer les observateurs pour les modèles
        PurchaseOrder::observe(PurchaseOrderObserver::class);
        PurchaseOrderItem::observe(PurchaseOrderItemObserver::class);
        GoodsReceiptItem::observe(GoodsReceiptItemObserver::class);
        Invoice::observe(InvoiceObserver::class);
        DeliveryNoteItem::observe(DeliveryNoteItemObserver::class);
        DeliveryNote::observe(DeliveryNoteObserver::class);
    }
}
