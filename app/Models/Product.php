<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // Si vous utilisez SoftDeletes
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // Ajouter
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Models\Traits\FormatsActivityLogEvents;

class Product extends Model
{
    use HasFactory, HasUuids, SoftDeletes, LogsActivity, FormatsActivityLogEvents; // Ajoutez SoftDeletes si utilisé dans la migration

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
        'stock_min_threshold',
        'stock_reorder_point',
        'stock_max_threshold',
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
        'stock_min_threshold' => 'decimal:2',
        'stock_reorder_point' => 'decimal:2',
        'stock_max_threshold' => 'decimal:2',
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
    
    /**
     * Scope pour les produits qui ont besoin d'être réapprovisionnés
     */
    public function scopeNeedsReordering($query)
    {
        // Le produit a besoin d'être commandé si la quantité en stock est inférieure ou égale au point de réapprovisionnement
        // ET que le point de réapprovisionnement est défini (non null)
        return $query->whereNotNull('stock_reorder_point')
                     ->whereColumn('stock_quantity', '<=', 'stock_reorder_point');
    }

    /**
     * Scope pour les produits dont le stock est inférieur au seuil minimum
     */
    public function scopeBelowMinimumStock($query)
    {
        // Le produit est en stock critique si la quantité en stock est inférieure ou égale au seuil minimum
        // ET que le seuil minimum est défini (non null)
        return $query->whereNotNull('stock_min_threshold')
                     ->whereColumn('stock_quantity', '<=', 'stock_min_threshold');
    }

    /**
     * Scope pour les produits dont le stock est supérieur au seuil maximum
     */
    public function scopeAboveMaximumStock($query)
    {
        // Le produit est en surstock si la quantité en stock est supérieure au seuil maximum
        // ET que le seuil maximum est défini (non null)
        return $query->whereNotNull('stock_max_threshold')
                     ->whereColumn('stock_quantity', '>', 'stock_max_threshold');
    }
    
    /**
     * Configuration des logs d'activité
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name', 'sku', 'description', 'product_category_id',
                'purchase_price', 'selling_price', 'stock_quantity',
                'is_active', 'stock_min_threshold', 'stock_reorder_point',
                'stock_max_threshold'
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Le produit '{$this->name}' (SKU: {$this->sku}) a été {$this->formatEventName($eventName)}.")
            ->useLogName('product_activity');
    }
}