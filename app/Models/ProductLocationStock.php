<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class ProductLocationStock extends Model
{
    use HasFactory, HasUuids, BelongsToTenant; // Pas de SoftDeletes ici, si le stock est à 0 on peut supprimer la ligne ou la garder.
    
    protected $fillable = ['tenant_id', 'product_id', 'location_id', 'quantity'];
    protected $casts = ['quantity' => 'decimal:2'];
    
    /**
     * Boot a trait for the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();
        
        // Nous utilisons le trait BelongsToTenant qui gère automatiquement le tenant_id
        // Pas besoin de logique personnalisée ici pour le tenant_id
    }

    public function product(): BelongsTo 
    { 
        return $this->belongsTo(Product::class); 
    }
    
    public function location(): BelongsTo 
    { 
        return $this->belongsTo(Location::class); 
    }
}
