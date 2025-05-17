<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute; // Pour l'accesseur de difference_quantity si pas de storedAs

class InventorySessionItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'inventory_session_id',
        'product_id',
        'theoretical_quantity',
        'counted_quantity',
        // 'difference_quantity', // Ne pas mettre ici si c'est une colonne `storedAs`
        'item_notes',
    ];

    protected $casts = [
        'theoretical_quantity' => 'decimal:2',
        'counted_quantity' => 'decimal:2',
        // 'difference_quantity' => 'decimal:2', // Si ce n'est pas storedAs
    ];

    // Les timestamps sont activés par défaut

    public function inventorySession(): BelongsTo
    {
        return $this->belongsTo(InventorySession::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Accesseur pour difference_quantity SI vous ne l'avez PAS défini comme `storedAs` dans la migration.
    // Si vous utilisez `storedAs`, cette méthode n'est pas nécessaire et peut même causer des conflits.
    /*
    protected function differenceQuantity(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => 
                (isset($attributes['counted_quantity']) && isset($attributes['theoretical_quantity'])) 
                ? (float)$attributes['counted_quantity'] - (float)$attributes['theoretical_quantity'] 
                : null,
        );
    }
    */

    // Il n'y a généralement pas de logique de 'boot' complexe pour les items d'inventaire avant la validation globale.
    // La mise à jour des totaux ou autres se fait souvent au niveau de la session d'inventaire ou lors de sa validation.
}