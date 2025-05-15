<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Quotation extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'quotation_number',
        'client_id',
        'user_id',
        'quotation_date',
        'expiry_date',
        'status',
        'subtotal',
        'taxes_amount',
        'discount_amount',
        'shipping_charges',
        'total_amount',
        'terms_and_conditions',
        'notes_to_client',
        'internal_notes',
    ];

    protected $casts = [
        'quotation_date' => 'date',
        'expiry_date' => 'date',
        'subtotal' => 'decimal:2',
        'taxes_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'shipping_charges' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public static function generateNextQuotationNumber(): string
    {
        $prefix = 'QTN-' . Carbon::now()->format('Ym') . '-'; // QTN pour Quotation
        // ... (logique similaire à Invoice::generateNextInvoiceNumber)
        $lastQuotation = self::where('quotation_number', 'like', $prefix . '%')
                           ->orderBy('quotation_number', 'desc')
                           ->first();
        $nextNumber = 1;
        if ($lastQuotation) {
            $lastSequentialPart = substr($lastQuotation->quotation_number, strlen($prefix));
            if (is_numeric($lastSequentialPart)) {
                $nextNumber = (int)$lastSequentialPart + 1;
            }
        }
        return $prefix . str_pad((string)$nextNumber, 4, '0', STR_PAD_LEFT);
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($quotation) {
            if (empty($quotation->quotation_number)) {
                $quotation->quotation_number = self::generateNextQuotationNumber();
            }
            if (empty($quotation->user_id) && auth()->check() && auth()->user() instanceof TenantUser) {
                $quotation->user_id = auth()->user()->getKey();
            }
            if (empty($quotation->quotation_date)) {
                $quotation->quotation_date = now()->toDateString();
            }
            if (empty($quotation->expiry_date) && $quotation->quotation_date) {
                // Exemple : expiration par défaut à 15 ou 30 jours
                $quotation->expiry_date = Carbon::parse($quotation->quotation_date)->addDays(15)->toDateString();
            }
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function calculateTotals() // Identique à Invoice
    {
        $subtotal = 0;
        $totalTaxes = 0;
        foreach ($this->items as $item) {
            $lineBaseForTax = ($item->quantity * $item->unit_price) * (1 - ($item->discount_percentage / 100));
            $subtotal += $lineBaseForTax;
            $totalTaxes += $lineBaseForTax * ($item->tax_rate / 100);
        }
        $this->subtotal = $subtotal;
        $this->taxes_amount = $totalTaxes;
        $this->total_amount = $this->subtotal + $this->taxes_amount - $this->discount_amount + $this->shipping_charges;
        $this->saveQuietly();
    }
}