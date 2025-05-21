<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\FormatsActivityLogEvents;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class Location extends Model
{
    use HasFactory, HasUuids, SoftDeletes, LogsActivity, FormatsActivityLogEvents, BelongsToTenant;
    
    protected $fillable = [
        'tenant_id',
        'warehouse_id', 
        'parent_location_id', 
        'name', 
        'barcode', 
        'location_type', 
        'is_pickable', 
        'is_storable', 
        'sequence'
    ];
    
    /**
     * Boot a trait for the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();
        
        // Événement déclenché avant chaque sauvegarde (création ou mise à jour)
        static::saving(function ($model) {
            // Vérifier et définir le tenant_id si nécessaire
            if (empty($model->tenant_id)) {
                // Première méthode: utiliser la fonction tenant()
                if (function_exists('tenant') && tenant()) {
                    $model->tenant_id = tenant()->getTenantKey();
                    \Log::info("Location Model saving: tenant_id défini via tenant(): {$model->tenant_id}");
                }
                // Deuxième méthode: extraire du nom de la base de données
                else {
                    try {
                        $currentDb = DB::connection()->getDatabaseName();
                        if (str_starts_with($currentDb, 'tenant_')) {
                            $model->tenant_id = str_replace('tenant_', '', $currentDb);
                            \Log::info("Location Model saving: tenant_id défini depuis le nom de la base de données: {$model->tenant_id}");
                        }
                    } catch (\Exception $e) {
                        \Log::error("Location Model saving: Impossible de déterminer le tenant_id: " . $e->getMessage());
                    }
                }
                
                // Vérification finale
                if (empty($model->tenant_id)) {
                    \Log::error("Location Model saving: Impossible de déterminer le tenant_id pour l'emplacement. Nom: {$model->name}");
                    // Dans un environnement de production, on pourrait lever une exception ici
                    // throw new \Exception("Impossible de déterminer le tenant_id pour l'emplacement {$model->name}.");
                }
            }
        });
        
        static::creating(function ($model) {
            // Générer un slug si nécessaire
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->name);
            }
        });
    }
    
    protected $casts = [
        'is_pickable' => 'boolean', 
        'is_storable' => 'boolean',
        'sequence' => 'integer'
    ];

    public function warehouse(): BelongsTo 
    { 
        return $this->belongsTo(Warehouse::class); 
    }
    
    public function parentLocation(): BelongsTo 
    { 
        return $this->belongsTo(Location::class, 'parent_location_id'); 
    }
    
    public function childLocations(): HasMany 
    { 
        return $this->hasMany(Location::class, 'parent_location_id'); 
    }
    
    public function productStocks(): HasMany 
    { 
        return $this->hasMany(ProductLocationStock::class); 
    }

    public function getFullPathAttribute(): string // Pour afficher "Entrepôt > Zone > Allée > Casier"
    {
        $path = $this->name;
        $parent = $this->parentLocation;
        while ($parent) {
            $path = $parent->name . ' > ' . $path;
            $parent = $parent->parentLocation;
        }
        return ($this->warehouse ? $this->warehouse->name . ' > ' : '') . $path;
    }
    
    /**
     * Calcule le nombre total d'articles stockés dans cet emplacement
     * en tenant compte des quantités réelles et pas seulement du nombre de relations
     *
     * @return float
     */
    public function getTotalStockedItemsAttribute(): float
    {
        return $this->productStocks()->sum('quantity');
    }
    
    /**
     * Calcule le nombre de produits différents stockés dans cet emplacement
     * (sans tenir compte des quantités)
     *
     * @return int
     */
    public function getUniqueProductsCountAttribute(): int
    {
        return $this->productStocks()->count();
    }
    
    /**
     * Configuration des logs d'activité
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'warehouse_id', 
                'parent_location_id', 
                'name', 
                'barcode', 
                'location_type', 
                'is_pickable', 
                'is_storable', 
                'sequence'
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "L'emplacement '{$this->name}' a été {$this->formatEventName($eventName)}.")
            ->useLogName('location_activity');
    }
}
