<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptItem extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = true; // Activer les timestamps si besoin

    protected $fillable = [
        'goods_receipt_id',
        'product_id',
        'purchase_order_item_id',
        'quantity_ordered',
        'quantity_received',
        'unit_price',
        // 'batch_number',
        // 'expiry_date',
        'notes',
    ];

    protected $casts = [
        'quantity_ordered' => 'integer',
        'quantity_received' => 'integer',
        'unit_price' => 'decimal:2',
        // 'expiry_date' => 'date',
    ];

    // Observer pour gérer la mise à jour du stock (voir Étape 4)

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }
}