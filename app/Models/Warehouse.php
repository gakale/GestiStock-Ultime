<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\FormatsActivityLogEvents;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Warehouse extends Model
{
    use HasFactory, HasUuids, SoftDeletes, LogsActivity, FormatsActivityLogEvents, BelongsToTenant;
    
    protected $fillable = ['name', 'address', 'is_active', 'tenant_id'];
    protected $casts = ['is_active' => 'boolean'];
    
    /**
     * Boot a trait for the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();
        
        // Ajout d'une protection supplémentaire pour s'assurer que tenant_id est toujours défini
        static::creating(function ($warehouse) {
            // Ne définir le tenant_id que s'il n'est pas déjà défini
            if (empty($warehouse->tenant_id)) {
                // Essayer d'abord avec la fonction tenant()
                if (function_exists('tenant') && tenant()) {
                    $warehouse->tenant_id = tenant()->getTenantKey();
                } else {
                    // Sinon, essayer de récupérer l'ID du tenant depuis la connexion de base de données
                    try {
                        $currentDb = \DB::connection()->getDatabaseName();
                        if (str_starts_with($currentDb, 'tenant_')) {
                            $warehouse->tenant_id = str_replace('tenant_', '', $currentDb);
                            \Log::info("Warehouse Model: tenant_id défini sur {$warehouse->tenant_id} pour l'entrepôt {$warehouse->name}");
                        } else {
                            \Log::warning("Warehouse Model: Impossible de déterminer le tenant_id à partir de la base de données {$currentDb}");
                        }
                    } catch (\Exception $e) {
                        \Log::error("Warehouse Model: Erreur lors de la définition du tenant_id: " . $e->getMessage());
                    }
                }
            }
        });
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    /**
     * Configuration des logs d'activité
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'address', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "L'entrepôt '{$this->name}' a été {$this->formatEventName($eventName)}.")
            ->useLogName('warehouse_activity');
    }
}