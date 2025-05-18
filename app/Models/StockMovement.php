<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo; // Pour la relation polymorphique

class StockMovement extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'product_id',
        'type',
        'quantity_changed',
        'new_stock_quantity_after_movement',
        'movement_date',
        'related_document_type',
        'related_document_id',
        'user_id',
        'reason',
        'notes',
    ];

    protected $casts = [
        'quantity_changed' => 'decimal:2',
        'new_stock_quantity_after_movement' => 'decimal:2',
        'movement_date' => 'datetime',
    ];

    public $timestamps = true; // created_at sera utile

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($movement) {
            if (empty($movement->movement_date)) {
                $movement->movement_date = now();
            }
            // L'utilisateur est généralement passé par le code qui crée le mouvement
            // if (empty($movement->user_id) && auth()->check() && auth()->user() instanceof TenantUser) {
            //     $movement->user_id = auth()->user()->getKey();
            // }

            // La logique de mise à jour de product.stock_quantity est mieux gérée par l'événement
            // qui *cause* le mouvement (ex: GoodsReceiptItemObserver).
            // Le 'new_stock_quantity_after_movement' est calculé et fourni lors de la création du mouvement.
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'user_id');
    }

    /**
     * Relation vers l'unité de mesure du mouvement (transaction_unit_id).
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(\App\Models\UnitOfMeasure::class, 'transaction_unit_id');
    }

    /**
     * Get the parent document model (GoodsReceipt, Invoice, etc.).
     */
    public function relatedDocument(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'related_document_type', 'related_document_id');
    }
}