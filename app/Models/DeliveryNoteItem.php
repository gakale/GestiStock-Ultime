<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryNoteItem extends Model
{
    use HasFactory, HasUuids;
    public $timestamps = true;

    protected $fillable = [
        'delivery_note_id',
        'product_id',
        'invoice_item_id',
        // 'sales_order_item_id',
        'product_name',
        'product_sku',
        'quantity_ordered',
        'quantity_shipped',
    ];

    protected $casts = [
        'quantity_ordered' => 'integer',
        'quantity_shipped' => 'integer',
    ];

    public function deliveryNote(): BelongsTo
    {
        return $this->belongsTo(DeliveryNote::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    // public function salesOrderItem(): BelongsTo
    // {
    //     return $this->belongsTo(SalesOrderItem::class);
    // }
}