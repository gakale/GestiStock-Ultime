<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // Si vous utilisez SoftDeletes
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
        'base_purchase_price_unit_id', // Nouveau champ pour l'unité de prix d'achat
        'selling_price',
        'base_selling_price_unit_id', // Nouveau champ pour l'unité de prix de vente
        // 'stock_quantity', // Supprimé car calculé à partir des emplacements
        'stock_min_threshold',
        'stock_reorder_point',
        'stock_max_threshold',
        'sku',
        'barcode',
        'is_active',
        'product_category_id',
        // Champs d'unité
        'stock_unit_id',
        'purchase_unit_id',
        'sales_unit_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'purchase_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        // 'stock_quantity' => 'integer', // Supprimé car calculé à partir des emplacements
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
            
            // Si l'unité de prix d'achat de base n'est pas définie, utiliser l'unité d'achat par défaut ou l'unité de stock
            if (empty($product->base_purchase_price_unit_id)) {
                $product->base_purchase_price_unit_id = $product->purchase_unit_id ?? $product->stock_unit_id;
            }
            // Si l'unité de prix de vente de base n'est pas définie, utiliser l'unité de vente par défaut ou l'unité de stock
            if (empty($product->base_selling_price_unit_id)) {
                $product->base_selling_price_unit_id = $product->sales_unit_id ?? $product->stock_unit_id;
            }
        });

        static::updating(function ($product) {
            if ($product->isDirty('name') && !$product->isDirty('slug')) {
                 $product->slug = Str::slug($product->name);
            }
            
            // Idem pour la mise à jour si les champs sont vidés
            if (empty($product->base_purchase_price_unit_id)) {
                $product->base_purchase_price_unit_id = $product->purchase_unit_id ?? $product->stock_unit_id;
            }
            if (empty($product->base_selling_price_unit_id)) {
                $product->base_selling_price_unit_id = $product->sales_unit_id ?? $product->stock_unit_id;
            }
        });
    }

    // Relation avec la catégorie de produit
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }
    
    // Relations vers les unités de mesure
    public function stockUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'stock_unit_id');
    }

    public function purchaseUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'purchase_unit_id');
    }

    public function salesUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'sales_unit_id');
    }
    
    // Nouvelles relations pour les unités de prix de base
    public function basePurchasePriceUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'base_purchase_price_unit_id');
    }

    public function baseSellingPriceUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'base_selling_price_unit_id');
    }
    
    /**
     * Relation avec les stocks par emplacement
     */
    public function productLocationStocks(): HasMany
    {
        return $this->hasMany(ProductLocationStock::class);
    }
    
    /**
     * Accesseur pour la quantité totale en stock (somme de tous les emplacements)
     */
    protected function stockQuantity(): Attribute
    {
        return Attribute::make(
            get: fn () => (float) $this->productLocationStocks()->sum('quantity')
        );
    }
    
    /**
     * Méthode pour obtenir la quantité dans un emplacement spécifique
     */
    public function getStockAtLocation(string $locationId): float
    {
        $stockEntry = $this->productLocationStocks()->where('location_id', $locationId)->first();
        return $stockEntry ? (float)$stockEntry->quantity : 0.0;
    }
    
    /**
     * Méthode pour mettre à jour le stock dans un emplacement spécifique
     * Utilisée par les StockMovements.
     */
    public function updateStockAtLocation(string $locationId, float $quantityChange): ProductLocationStock
    {
        $stockEntry = $this->productLocationStocks()->firstOrCreate(
            ['location_id' => $locationId], // Conditions de recherche
            ['quantity' => 0]                // Valeurs par défaut si création
        );
        $stockEntry->increment('quantity', $quantityChange); // Ou decrement si négatif
        return $stockEntry;
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
                'purchase_price', 'base_purchase_price_unit_id', // Ajouter l'unité de prix d'achat
                'selling_price', 'base_selling_price_unit_id', // Ajouter l'unité de prix de vente
                'stock_quantity',
                'is_active', 'stock_min_threshold', 'stock_reorder_point',
                'stock_max_threshold',
                'stock_unit_id', 'purchase_unit_id', 'sales_unit_id'
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Le produit '{$this->name}' (SKU: {$this->sku}) a été {$this->formatEventName($eventName)}.")
            ->useLogName('product_activity');
    }
}