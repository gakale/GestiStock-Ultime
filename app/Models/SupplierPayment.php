<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;
use App\Models\Traits\FormatsActivityLogEvents;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class SupplierPayment extends Model
{
    use HasFactory, HasUuids, SoftDeletes, LogsActivity, FormatsActivityLogEvents;

    protected $fillable = [
        'payment_reference', 'supplier_id', 'user_id', 'payment_method_name',
        'payment_date', 'amount', 'notes', 'transaction_id',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public static function generateNextPaymentReference(): string
    {
        $prefix = 'SPAY-' . Carbon::now()->format('Ym') . '-'; // SPAY pour Supplier Payment
        $lastPayment = self::where('payment_reference', 'like', $prefix . '%')
                            ->orderBy('payment_reference', 'desc')
                            ->first();
        $nextNumber = 1;
        if ($lastPayment) {
            $lastSequentialPart = substr($lastPayment->payment_reference, strlen($prefix));
            if (is_numeric($lastSequentialPart)) {
                $nextNumber = (int)$lastSequentialPart + 1;
            }
        }
        return $prefix . str_pad((string)$nextNumber, 4, '0', STR_PAD_LEFT);
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($payment) {
            if (empty($payment->payment_reference)) {
                $payment->payment_reference = self::generateNextPaymentReference();
            }
            if (empty($payment->user_id) && auth()->check() && auth()->user() instanceof TenantUser) {
                $payment->user_id = auth()->user()->getKey();
            }
        });
    }

    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function user(): BelongsTo { return $this->belongsTo(TenantUser::class, 'user_id'); }
    // public function paymentMethod(): BelongsTo { return $this->belongsTo(PaymentMethod::class); }

    public function supplierInvoices(): BelongsToMany
    {
        return $this->belongsToMany(SupplierInvoice::class, 'supplier_invoice_payment', 'supplier_payment_id', 'supplier_invoice_id')
                    ->withPivot('amount_applied')
                    ->withTimestamps();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['payment_reference', 'supplier_id', 'amount', 'payment_date', 'payment_method_name'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Le paiement fournisseur '{$this->payment_reference}' de {$this->amount} a été {$this->formatEventName($eventName)}.")
            ->useLogName('supplier_payment_activity');
    }
}