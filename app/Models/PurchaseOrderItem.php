<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'product_name',
        'product_sku',
        'transaction_unit_id', // Nouveau champ pour l'unité de commande
        'quantity',            // Est maintenant la transaction_quantity
        'unit_price',          // Est maintenant le transaction_unit_price
        'discount_percentage',
        'tax_rate',
        'line_total',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',    // Changé de integer à decimal pour plus de flexibilité
        'unit_price' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    // Pas de timestamps par défaut sur les items si pas nécessaire
    public $timestamps = true; // Ou false si vous ne les voulez pas

    protected static function boot()
    {
        parent::boot();

        // Calculer line_total avant de sauvegarder
        static::saving(function ($item) {
            $basePrice = $item->quantity * $item->unit_price;
            $discountAmount = $basePrice * ($item->discount_percentage / 100);
            $priceAfterDiscount = $basePrice - $discountAmount;
            $taxAmount = $priceAfterDiscount * ($item->tax_rate / 100);
            $item->line_total = $priceAfterDiscount + $taxAmount;
        });

        // Mettre à jour les totaux de la commande parente après sauvegarde/suppression d'un item
        static::saved(function ($item) {
            $item->purchaseOrder?->calculateTotals();
        });

        static::deleted(function ($item) {
            $item->purchaseOrder?->calculateTotals();
        });
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
    
    /**
     * Relation avec l'unité de transaction (unité dans laquelle le produit est commandé)
     */
    public function transactionUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'transaction_unit_id');
    }
}