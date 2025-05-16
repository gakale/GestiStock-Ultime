<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierCreditNoteItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'supplier_credit_note_id',
        'product_id',
        'description',
        'quantity',
        'unit', // Peut être utile si vous avez des unités variables
        'unit_price',
        'tax_rate', // Taux de TVA en % (ex: 20.00)
        'tax_amount', // Calculé: (quantity * unit_price) * (tax_rate / 100)
        'line_total', // Calculé: (quantity * unit_price) + tax_amount
    ];

    protected $casts = [
        'quantity' => 'decimal:2', // Ou integer si vous ne gérez pas les fractions
        'unit_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    // Les timestamps sont activés par défaut (public $timestamps = true;)

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            // Calculer tax_amount et line_total
            $basePrice = (float)$item->quantity * (float)$item->unit_price;
            $item->tax_amount = $basePrice * ((float)$item->tax_rate / 100);
            $item->line_total = $basePrice + $item->tax_amount;

            // Optionnel: si la description est vide, prendre celle du produit
            if (empty($item->description) && $item->product_id) {
                $product = Product::find($item->product_id);
                if ($product) {
                    $item->description = $product->name; // ou $product->description si vous avez
                }
            }
        });

        // Mettre à jour les totaux de l'avoir parent
        static::saved(function ($item) {
            $item->supplierCreditNote?->calculateTotals();
        });

        static::deleted(function ($item) {
            $item->supplierCreditNote?->calculateTotals();
        });
    }

    public function supplierCreditNote(): BelongsTo
    {
        return $this->belongsTo(SupplierCreditNote::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}