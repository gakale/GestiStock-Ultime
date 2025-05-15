<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class CreditNote extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'credit_note_number', 'client_id', 'invoice_id', 'user_id',
        'credit_note_date', 'reason', 'status',
        'subtotal', 'taxes_amount', 'total_amount', // Pas de discount global ou shipping sur un avoir simple
        'restock_items', 'internal_notes',
    ];

    protected $casts = [
        'credit_note_date' => 'date',
        'subtotal' => 'decimal:2',
        'taxes_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'restock_items' => 'boolean',
    ];

    public static function generateNextCreditNoteNumber(): string
    {
        $prefix = 'CN-' . Carbon::now()->format('Ym') . '-'; // CN pour Credit Note
        // ... (logique de génération similaire)
        $lastCN = self::where('credit_note_number', 'like', $prefix . '%')->orderBy('credit_note_number', 'desc')->first();
        $nextNumber = 1;
        if ($lastCN) {
            $lastSequentialPart = substr($lastCN->credit_note_number, strlen($prefix));
            if (is_numeric($lastSequentialPart)) $nextNumber = (int)$lastSequentialPart + 1;
        }
        return $prefix . str_pad((string)$nextNumber, 4, '0', STR_PAD_LEFT);
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($cn) {
            if (empty($cn->credit_note_number)) {
                $cn->credit_note_number = self::generateNextCreditNoteNumber();
            }
            if (empty($cn->user_id) && auth()->check() && auth()->user() instanceof TenantUser) {
                $cn->user_id = auth()->user()->getKey();
            }
            if (empty($cn->credit_note_date)) {
                $cn->credit_note_date = now()->toDateString();
            }
        });
    }

    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); } // Facture d'origine
    public function user(): BelongsTo { return $this->belongsTo(TenantUser::class, 'user_id'); }
    public function items(): HasMany { return $this->hasMany(CreditNoteItem::class); }

    public function calculateTotals() // Similaire à Invoice/Quotation
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
        // Pour un avoir, le total est généralement le sous-total + taxes (pas de remises globales ou frais de port à soustraire)
        $this->total_amount = $this->subtotal + $this->taxes_amount;
        $this->saveQuietly();
    }
}