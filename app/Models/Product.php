<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // Si vous utilisez SoftDeletes
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // Ajouter


class Product extends Model
{
    use HasFactory, HasUuids, SoftDeletes; // Ajoutez SoftDeletes si utilisé dans la migration

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'purchase_price',
        'selling_price',
        'stock_quantity',
        'sku',
        'barcode',
        'is_active',
        'product_category_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'purchase_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'stock_quantity' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Boot a trait for the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($product) {
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->name);
            }
            // Générer un SKU simple si vide, à améliorer plus tard
            if (empty($product->sku)) {
                $product->sku = 'PROD-' . strtoupper(Str::random(6));
            }
        });

        static::updating(function ($product) {
            if ($product->isDirty('name') && !$product->isDirty('slug')) {
                 $product->slug = Str::slug($product->name);
            }
        });
    }

    // Relation (à ajouter plus tard quand on aura les catégories)
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }
}