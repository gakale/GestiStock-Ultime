<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderItem extends Model
{
    use HasFactory, HasUuids;
    public $timestamps = true;

    protected $fillable = [
        'sales_order_id', 'product_id', 'product_name', 'product_sku', 'description',
        'quantity_ordered', 'quantity_shipped', 'quantity_invoiced',
        'unit_price', 'discount_percentage', 'tax_rate', 'line_total',
    ];

    protected $casts = [
        'quantity_ordered' => 'integer', 'quantity_shipped' => 'integer', 'quantity_invoiced' => 'integer',
        'unit_price' => 'decimal:2', /* ... autres casts ... */ 'line_total' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();
        static::saving(function ($item) { // Calcul du total de la ligne
            $basePrice = $item->quantity_ordered * $item->unit_price;
            $discountAmount = $basePrice * ($item->discount_percentage / 100);
            $priceAfterDiscount = $basePrice - $discountAmount;
            $taxAmount = $priceAfterDiscount * ($item->tax_rate / 100);
            $item->line_total = $priceAfterDiscount + $taxAmount;
        });
        static::saved(fn ($item) => $item->salesOrder?->calculateTotals());
        static::deleted(fn ($item) => $item->salesOrder?->calculateTotals());
    }

    public function salesOrder(): BelongsTo { return $this->belongsTo(SalesOrder::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}