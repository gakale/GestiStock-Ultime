<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class GoodsReceipt extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'receipt_number',
        'purchase_order_id',
        'supplier_id',
        'supplier_delivery_note_number',
        'receipt_date',
        'received_by_user_id',
        'status',
        'notes',
    ];

    protected $casts = [
        'receipt_date' => 'date',
    ];

    public static function generateNextReceiptNumber(): string
    {
        $prefix = 'BR-' . Carbon::now()->format('Ym') . '-'; // BR pour Bon de Réception
        $lastReceipt = self::where('receipt_number', 'like', $prefix . '%')
                           ->orderBy('receipt_number', 'desc')
                           ->first();
        $nextNumber = 1;
        if ($lastReceipt) {
            $lastSequentialPart = substr($lastReceipt->receipt_number, strlen($prefix));
            if (is_numeric($lastSequentialPart)) {
                $nextNumber = (int)$lastSequentialPart + 1;
            }
        }
        return $prefix . str_pad((string)$nextNumber, 4, '0', STR_PAD_LEFT);
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($receipt) {
            if (empty($receipt->receipt_number)) {
                $receipt->receipt_number = self::generateNextReceiptNumber();
            }
            if (empty($receipt->received_by_user_id) && auth()->check() && auth()->user() instanceof TenantUser) {
                $receipt->received_by_user_id = auth()->user()->getKey();
            }
            if (empty($receipt->receipt_date)) {
                $receipt->receipt_date = now()->toDateString();
            }
        });

        // L'événement 'created' est un bon endroit pour mettre à jour le stock
        // et le statut de la commande fournisseur après que la réception et ses items sont sauvegardés.
        // Cependant, cela peut être complexe si les items sont créés après le header.
        // Un observer sur GoodsReceiptItem est souvent plus adapté pour les mises à jour de stock par ligne.
        // Et un service/job pour finaliser la mise à jour du statut de PurchaseOrder.
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function receivedByUser(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'received_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }
}