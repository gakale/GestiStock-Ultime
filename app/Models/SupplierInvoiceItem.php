<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
// Pas de logs d'activité pour les items pour l'instant, pour éviter la verbosité.

class SupplierInvoiceItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'supplier_invoice_id', 'product_id', 'description', 'quantity',
        'unit_price', 'discount_percentage', 'tax_rate', 'line_total',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();
        static::saving(function ($item) {
            $basePrice = (float)$item->quantity * (float)$item->unit_price;
            $discountAmount = $basePrice * ((float)$item->discount_percentage / 100);
            $priceAfterDiscount = $basePrice - $discountAmount;
            $taxAmount = $priceAfterDiscount * ((float)$item->tax_rate / 100);
            $item->line_total = $priceAfterDiscount + $taxAmount;
        });

        static::saved(function ($item) { $item->supplierInvoice?->calculateTotals(); });
        static::deleted(function ($item) { $item->supplierInvoice?->calculateTotals(); });
    }

    public function supplierInvoice(): BelongsTo { return $this->belongsTo(SupplierInvoice::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}