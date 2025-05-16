<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class SupplierCreditNote extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'credit_note_number',
        'supplier_id',
        'purchase_order_id',
        'goods_receipt_id',
        'user_id',
        'credit_note_date',
        'reason',
        'status',
        'subtotal',
        'taxes_amount',
        'total_amount',
        'items_returned_to_supplier_stock', // Ce champ est important pour la logique de stock
        'internal_notes',
    ];

    protected $casts = [
        'credit_note_date' => 'date',
        'subtotal' => 'decimal:2',
        'taxes_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'items_returned_to_supplier_stock' => 'boolean',
    ];

    public static function generateNextCreditNoteNumber(): string
    {
        // SCN pour Supplier Credit Note
        $prefix = 'SCN-' . Carbon::now()->format('Ym') . '-';
        $lastSCN = self::where('credit_note_number', 'like', $prefix . '%')
                        ->orderBy('credit_note_number', 'desc')
                        ->first();
        $nextNumber = 1;
        if ($lastSCN) {
            $lastSequentialPart = substr($lastSCN->credit_note_number, strlen($prefix));
            if (is_numeric($lastSequentialPart)) {
                $nextNumber = (int)$lastSequentialPart + 1;
            }
        }
        return $prefix . str_pad((string)$nextNumber, 4, '0', STR_PAD_LEFT);
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($scn) {
            if (empty($scn->credit_note_number)) {
                $scn->credit_note_number = self::generateNextCreditNoteNumber();
            }
            // Assurer que l'utilisateur est un TenantUser et est authentifié
            if (empty($scn->user_id) && auth()->check() && auth()->user() instanceof TenantUser) {
                $scn->user_id = auth()->user()->getKey();
            }
            if (empty($scn->credit_note_date)) {
                $scn->credit_note_date = now()->toDateString();
            }
            if (is_null($scn->status)) { // Mettre un statut par défaut si non fourni
                $scn->status = 'draft';
            }
        });
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function user(): BelongsTo // Utilisateur du tenant
    {
        return $this->belongsTo(TenantUser::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplierCreditNoteItem::class);
    }

    public function calculateTotals()
    {
        $subtotal = 0;
        $totalTaxes = 0;

        foreach ($this->items as $item) {
            // Le line_total de l'item devrait déjà inclure sa taxe
            // $subtotal += ($item->quantity * $item->unit_price); // Sous-total avant taxe
            // $totalTaxes += $item->tax_amount;
            // Pour plus de précision, recalculons ici basé sur les données brutes des lignes
            $lineBase = (float)$item->quantity * (float)$item->unit_price;
            $subtotal += $lineBase;
            $totalTaxes += $lineBase * ((float)$item->tax_rate / 100);
        }

        $this->subtotal = $subtotal;
        $this->taxes_amount = $totalTaxes;
        $this->total_amount = $this->subtotal + $this->taxes_amount;

        $this->saveQuietly();
    }
}