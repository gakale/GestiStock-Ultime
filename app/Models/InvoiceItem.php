<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Models\Traits\FormatsActivityLogEvents;

class InvoiceItem extends Model
{
    use HasFactory, HasUuids, LogsActivity, FormatsActivityLogEvents;

    public $timestamps = true;

    protected $fillable = [
        'invoice_id',
        'product_id',
        'product_name',
        'product_sku',
        'description',
        'quantity',
        'unit_price',
        'discount_percentage',
        'tax_rate',
        'line_total',
        'sales_order_item_id',
    ];

    protected $casts = [
        'quantity' => 'integer',
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
            $item->invoice?->calculateTotals();
            // La décrémentation de stock sera gérée par un observer sur InvoiceItem (état "confirmé" de la facture)
        });

        static::deleted(function ($item) {
            $item->invoice?->calculateTotals();
            // L'incrémentation de stock (si annulation) sera gérée par un observer
        });
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
    
    public function salesOrderItem(): BelongsTo
    {
        return $this->belongsTo(SalesOrderItem::class);
    }
    
    /**
     * Configuration des logs d'activité
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'invoice_id', 'product_id', 'product_name', 'product_sku',
                'description', 'quantity', 'unit_price', 'discount_percentage',
                'tax_rate', 'line_total'
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(function(string $eventName) {
                $invoiceNumber = $this->invoice ? $this->invoice->invoice_number : 'inconnue';
                return "La ligne de facture pour '{$this->product_name}' (Facture n°{$invoiceNumber}) a été {$this->formatEventName($eventName)}.";
            })
            ->useLogName('invoice_item_activity');
    }
}