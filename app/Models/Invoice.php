<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Invoice extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'invoice_number',
        'client_id',
        'user_id',
        'invoice_date',
        'due_date',
        'status',
        'subtotal',
        'taxes_amount',
        'discount_amount',
        'shipping_charges',
        'total_amount',
        'amount_paid',
        'payment_terms',
        'notes_to_client',
        'internal_notes',
        // 'source_document_type',
        // 'source_document_id',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'taxes_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'shipping_charges' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
    ];

    public static function generateNextInvoiceNumber(): string
    {
        $prefix = 'INV-' . Carbon::now()->format('Ym') . '-';
        $lastInvoice = self::where('invoice_number', 'like', $prefix . '%')
                           ->orderBy('invoice_number', 'desc')
                           ->first();
        $nextNumber = 1;
        if ($lastInvoice) {
            $lastSequentialPart = substr($lastInvoice->invoice_number, strlen($prefix));
            if (is_numeric($lastSequentialPart)) {
                $nextNumber = (int)$lastSequentialPart + 1;
            }
        }
        return $prefix . str_pad((string)$nextNumber, 4, '0', STR_PAD_LEFT);
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($invoice) {
            if (empty($invoice->invoice_number)) {
                $invoice->invoice_number = self::generateNextInvoiceNumber();
            }
            if (empty($invoice->user_id) && auth()->check() && auth()->user() instanceof TenantUser) {
                $invoice->user_id = auth()->user()->getKey();
            }
            if (empty($invoice->invoice_date)) {
                $invoice->invoice_date = now()->toDateString();
            }
            if (empty($invoice->due_date) && $invoice->invoice_date) {
                // Exemple : échéance par défaut à 30 jours
                $invoice->due_date = Carbon::parse($invoice->invoice_date)->addDays(30)->toDateString();
            }
        });

        // L'événement 'created' ou un observer sur InvoiceItem gérera la sortie de stock
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
        return $this->hasMany(InvoiceItem::class);
    }

    public function calculateTotals()
    {
        $subtotal = 0;
        $totalTaxes = 0;

        foreach ($this->items as $item) {
            // (Quantité * Prix Unitaire) - Remise sur la ligne
            $lineBaseForTax = ($item->quantity * $item->unit_price) * (1 - ($item->discount_percentage / 100));
            $subtotal += $lineBaseForTax; // Le sous-total est avant les taxes de ligne
            $totalTaxes += $lineBaseForTax * ($item->tax_rate / 100);
        }

        $this->subtotal = $subtotal;
        $this->taxes_amount = $totalTaxes;
        // total_amount = (sous-total des lignes après remises de ligne) + (taxes des lignes) - (remise globale) + (frais de port)
        $this->total_amount = $this->subtotal + $this->taxes_amount - $this->discount_amount + $this->shipping_charges;
        $this->saveQuietly();
    }
}