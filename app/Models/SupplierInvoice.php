<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;
use App\Models\Traits\FormatsActivityLogEvents; // Si vous l'utilisez
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class SupplierInvoice extends Model
{
    use HasFactory, HasUuids, SoftDeletes, LogsActivity, FormatsActivityLogEvents;

    protected $fillable = [
        'supplier_id', 'user_id', 'purchase_order_id', 'goods_receipt_id',
        'supplier_invoice_number', 'invoice_date', 'due_date', 'status',
        'subtotal', 'taxes_amount', 'shipping_charges', 'total_amount', 'amount_paid',
        'notes', 'attachment_path',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'taxes_amount' => 'decimal:2',
        'shipping_charges' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($invoice) {
            if (empty($invoice->user_id) && auth()->check() && auth()->user() instanceof TenantUser) {
                $invoice->user_id = auth()->user()->getKey();
            }
            if (empty($invoice->status)) {
                $invoice->status = 'pending';
            }
        });

        // Mettre à jour le statut en fonction du montant payé
        static::saving(function ($invoice) {
            if ($invoice->total_amount > 0) {
                if ($invoice->amount_paid >= $invoice->total_amount) {
                    $invoice->status = 'paid';
                } elseif ($invoice->amount_paid > 0 && $invoice->amount_paid < $invoice->total_amount) {
                    $invoice->status = 'partially_paid';
                } elseif ($invoice->amount_paid == 0 && $invoice->status !== 'cancelled' && $invoice->due_date && $invoice->due_date->isPast()) {
                     $invoice->status = 'overdue';
                } elseif ($invoice->amount_paid == 0 && $invoice->status !== 'cancelled') {
                    $invoice->status = 'pending';
                }
            } else { // Facture à 0 (ex: note de crédit fournisseur)
                $invoice->status = 'paid'; // Considérée comme soldée
            }
        });
    }

    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function user(): BelongsTo { return $this->belongsTo(TenantUser::class, 'user_id'); }
    public function purchaseOrder(): BelongsTo { return $this->belongsTo(PurchaseOrder::class); }
    public function goodsReceipt(): BelongsTo { return $this->belongsTo(GoodsReceipt::class); }
    public function items(): HasMany { return $this->hasMany(SupplierInvoiceItem::class); }
    public function payments(): BelongsToMany
    {
        return $this->belongsToMany(SupplierPayment::class, 'supplier_invoice_payment', 'supplier_invoice_id', 'supplier_payment_id')
                    ->withPivot('amount_applied')
                    ->withTimestamps();
    }

    public function calculateTotals()
    {
        $subtotal = 0;
        $totalTaxes = 0;
        foreach ($this->items as $item) {
            $basePrice = (float)$item->quantity * (float)$item->unit_price;
            $discountAmount = $basePrice * ((float)$item->discount_percentage / 100);
            $priceAfterDiscount = $basePrice - $discountAmount;
            $subtotal += $priceAfterDiscount;
            $totalTaxes += $priceAfterDiscount * ((float)$item->tax_rate / 100);
        }
        $this->subtotal = $subtotal;
        $this->taxes_amount = $totalTaxes;
        $this->total_amount = $this->subtotal + $this->taxes_amount + (float)$this->shipping_charges;
        $this->saveQuietly(); // Pour éviter boucle avec l'event saving
    }

    public function getBalanceAttribute(): float
    {
        return (float)$this->total_amount - (float)$this->amount_paid;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['supplier_invoice_number', 'supplier_id', 'status', 'total_amount', 'due_date', 'amount_paid'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "La facture fournisseur '{$this->supplier_invoice_number}' a été {$this->formatEventName($eventName)}.")
            ->useLogName('supplier_invoice_activity');
    }
}