<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class DeliveryNote extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'delivery_note_number',
        'client_id',
        'user_id',
        'invoice_id',
        'sales_order_id',
        'delivery_date',
        'status',
        'shipping_address_line1',
        'shipping_address_line2',
        'shipping_city',
        'shipping_postal_code',
        'shipping_country',
        'tracking_number',
        'carrier_name',
        'notes',
    ];

    protected $casts = [
        'delivery_date' => 'date',
    ];

    public static function generateNextDeliveryNoteNumber(): string
    {
        $prefix = 'BL-' . Carbon::now()->format('Ym') . '-'; // BL pour Bon de Livraison
        // ... (logique similaire à Invoice::generateNextInvoiceNumber)
        $lastDN = self::where('delivery_note_number', 'like', $prefix . '%')
                           ->orderBy('delivery_note_number', 'desc')
                           ->first();
        $nextNumber = 1;
        if ($lastDN) {
            $lastSequentialPart = substr($lastDN->delivery_note_number, strlen($prefix));
            if (is_numeric($lastSequentialPart)) {
                $nextNumber = (int)$lastSequentialPart + 1;
            }
        }
        return $prefix . str_pad((string)$nextNumber, 4, '0', STR_PAD_LEFT);
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($dn) {
            if (empty($dn->delivery_note_number)) {
                $dn->delivery_note_number = self::generateNextDeliveryNoteNumber();
            }
            if (empty($dn->user_id) && auth()->check() && auth()->user() instanceof TenantUser) {
                $dn->user_id = auth()->user()->getKey();
            }
            if (empty($dn->delivery_date)) {
                $dn->delivery_date = now()->toDateString();
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

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryNoteItem::class);
    }
}