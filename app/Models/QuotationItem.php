<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItem extends Model
{
    use HasFactory, HasUuids;
    public $timestamps = true;

    protected $fillable = [
        'quotation_id',
        'product_id',
        'product_name',
        'product_sku',
        'description',
        'quantity',
        'unit_price',
        'discount_percentage',
        'tax_rate',
        'transaction_unit_id',
        'line_total',
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
            $basePrice = $item->quantity * $item->unit_price;
            $discountAmount = $basePrice * ($item->discount_percentage / 100);
            $priceAfterDiscount = $basePrice - $discountAmount;
            $taxAmount = $priceAfterDiscount * ($item->tax_rate / 100);
            $item->line_total = $priceAfterDiscount + $taxAmount;
        });

        static::saved(function ($item) {
            $item->quotation?->calculateTotals();
        });

        static::deleted(function ($item) {
            $item->quotation?->calculateTotals();
        });
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
    
    /**
     * Relation vers l'unité de mesure utilisée pour cette ligne de devis
     */
    public function transactionUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'transaction_unit_id');
    }
}