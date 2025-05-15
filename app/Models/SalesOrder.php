<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class SalesOrder extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'order_number', 'client_id', 'user_id', 'quotation_id',
        'order_date', 'expected_shipment_date', 'status',
        'subtotal', 'taxes_amount', 'discount_amount', 'shipping_charges', 'total_amount',
        'client_po_reference', 'shipping_address_details', 'notes',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_shipment_date' => 'date',
        'subtotal' => 'decimal:2', /* ... autres casts décimaux ... */
        'total_amount' => 'decimal:2',
    ];

    public static function generateNextOrderNumber(): string
    {
        $prefix = 'SO-' . Carbon::now()->format('Ym') . '-'; // SO pour Sales Order
        // ... (logique de génération similaire)
        $lastOrder = self::where('order_number', 'like', $prefix . '%')->orderBy('order_number', 'desc')->first();
        $nextNumber = 1;
        if ($lastOrder) {
            $lastSequentialPart = substr($lastOrder->order_number, strlen($prefix));
            if (is_numeric($lastSequentialPart)) $nextNumber = (int)$lastSequentialPart + 1;
        }
        return $prefix . str_pad((string)$nextNumber, 4, '0', STR_PAD_LEFT);
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = self::generateNextOrderNumber();
            }
            if (empty($order->user_id) && auth()->check() && auth()->user() instanceof TenantUser) {
                $order->user_id = auth()->user()->getKey();
            }
            if (empty($order->order_date)) {
                $order->order_date = now()->toDateString();
            }
        });
    }

    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function user(): BelongsTo { return $this->belongsTo(TenantUser::class, 'user_id'); }
    public function quotation(): BelongsTo { return $this->belongsTo(Quotation::class); }
    public function items(): HasMany { return $this->hasMany(SalesOrderItem::class); }

    public function calculateTotals() // Identique à Invoice/Quotation
    {
        // ... (copier la logique de calcul des totaux depuis Invoice ou Quotation)
        $subtotal = 0;
        $totalTaxes = 0;
        foreach ($this->items as $item) {
            $lineBaseForTax = ($item->quantity_ordered * $item->unit_price) * (1 - ($item->discount_percentage / 100));
            $subtotal += $lineBaseForTax;
            $totalTaxes += $lineBaseForTax * ($item->tax_rate / 100);
        }
        $this->subtotal = $subtotal;
        $this->taxes_amount = $totalTaxes;
        $this->total_amount = $this->subtotal + $this->taxes_amount - $this->discount_amount + $this->shipping_charges;
        $this->saveQuietly();
    }

    // Méthodes pour vérifier si tout a été livré/facturé (à développer plus tard)
    // public function isFullyShipped(): bool
    // public function isFullyInvoiced(): bool
}