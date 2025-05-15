<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class PaymentReceived extends Model
{
    use HasFactory, HasUuids;
    
    protected $table = 'payments_received';

    protected $fillable = [
        'payment_reference',
        'client_id',
        'invoice_id',
        'payment_date',
        'amount',
        'payment_method',
        'transaction_id',
        'notes',
        'user_id',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public static function generateNextPaymentReference(): string
    {
        $prefix = 'PMT-REC-' . Carbon::now()->format('Ym') . '-';
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
            if (empty($payment->payment_date)) {
                $payment->payment_date = now()->toDateString();
            }
        });

        // Observer pour mettre à jour Invoice.amount_paid et Invoice.status
        // (voir Étape 4)
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'user_id');
    }
}