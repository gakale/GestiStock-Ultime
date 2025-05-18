<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\FormatsActivityLogEvents; // Si vous l'utilisez
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class UnitOfMeasure extends Model
{
    use HasFactory, HasUuids, SoftDeletes, LogsActivity, FormatsActivityLogEvents;

    protected $fillable = [
        'name',
        'symbol',
        'type',
        'base_unit_id',
        'conversion_factor',
        'is_active',
    ];

    protected $casts = [
        'conversion_factor' => 'decimal:5',
        'is_active' => 'boolean',
    ];

    // Relation vers l'unité de base (pour les conversions)
    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'base_unit_id');
    }

    // Relation vers les unités qui ont celle-ci comme base (inverse)
    public function derivedUnits(): HasMany
    {
        return $this->hasMany(UnitOfMeasure::class, 'base_unit_id');
    }

    // Méthode pour vérifier si c'est une unité de base
    public function isBaseUnit(): bool
    {
        return $this->base_unit_id === null;
    }

    // Méthodes de conversion (simplifiées pour l'instant, peuvent devenir plus complexes)
    /**
     * Convertit une quantité de cette unité vers son unité de base.
     * Exemple: 2 Cartons (de 12 pièces) -> 2 * 12 = 24 Pièces.
     */
    public function convertToBaseUnit(float $quantity): float
    {
        if ($this->isBaseUnit()) {
            return $quantity;
        }
        // On suppose que le conversion_factor est "combien de base_unit dans this_unit"
        return $quantity * (float)$this->conversion_factor;
    }

    /**
     * Convertit une quantité d'une unité de base vers cette unité.
     * Exemple: 24 Pièces -> 24 / 12 = 2 Cartons (de 12 pièces).
     * Attention: ne fonctionne que si cette unité est une dérivée directe de l'unité de base fournie.
     */
    public function convertFromBaseUnit(float $quantityInBaseUnit): float
    {
        if ($this->isBaseUnit()) {
            return $quantityInBaseUnit; // Aucune conversion si c'est déjà l'unité de base
        }
        if ((float)$this->conversion_factor == 0) {
            throw new \InvalidArgumentException("Le facteur de conversion ne peut pas être zéro pour l'unité {$this->name}.");
        }
        return $quantityInBaseUnit / (float)$this->conversion_factor;
    }

    /**
     * Accesseur pour obtenir le nom avec le symbole de l'unité
     * Exemple: "Kilogramme (kg)"
     */
    public function getNameWithSymbolAttribute(): string
    {
        return "{$this->name} ({$this->symbol})";
    }
    
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'symbol', 'type', 'base_unit_id', 'conversion_factor', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "L'unité de mesure '{$this->name}' ({$this->symbol}) a été {$this->formatEventName($eventName)}.")
            ->useLogName('uom_activity');
    }
}