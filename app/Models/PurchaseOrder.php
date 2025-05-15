<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PurchaseOrder extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'order_number',
        'supplier_id',
        'user_id',
        'order_date',
        'expected_delivery_date',
        'status',
        'subtotal',
        'taxes',
        'discount_amount',
        'shipping_cost',
        'total_amount',
        'supplier_reference',
        'notes_to_supplier',
        'internal_notes',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_delivery_date' => 'date',
        'subtotal' => 'decimal:2',
        'taxes' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    /**
     * Génère le prochain numéro de commande
     *
     * @return string
     */
    public static function generateNextOrderNumber(): string
    {
        $prefix = 'PO-' . now()->format('Ym') . '-'; // Ex: PO-202401-
        $lastOrder = static::where('order_number', 'like', $prefix . '%')
                        ->orderBy('order_number', 'desc')
                        ->first();
        $nextNumber = 1;
        if ($lastOrder) {
            $lastSequentialPart = substr($lastOrder->order_number, strlen($prefix));
            if (is_numeric($lastSequentialPart)) {
                $nextNumber = (int)$lastSequentialPart + 1;
            }
        }
        return $prefix . str_pad((string)$nextNumber, 4, '0', STR_PAD_LEFT);
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = self::generateNextOrderNumber();
            }
            // LIGNE COMMENTÉE CI-DESSOUS :
            // if (empty($order->user_id) && auth()->check() && auth()->user() instanceof TenantUser) {
            //     $order->user_id = auth()->user()->getKey();
            // }
            if (empty($order->order_date)) {
                $order->order_date = now();
            }
        });
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user(): BelongsTo // Utilisateur du tenant qui a créé
    {
        return $this->belongsTo(TenantUser::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    // Méthode pour calculer les totaux (à appeler lors de la sauvegarde des items)
    public function calculateTotals()
    {
        $subtotal = 0;
        foreach ($this->items as $item) {
            // Calcul simplifié, sans prise en compte des remises/taxes par ligne pour l'instant pour le subtotal global
            // Le line_total de l'item devrait déjà inclure ses propres taxes/remises
            $subtotal += (float)$item->quantity * (float)$item->unit_price; // Conversion explicite en float
        }
        $this->subtotal = $subtotal;

        // Logique pour taxes, discount_amount (global) à ajouter ici
        // Pour l'instant, on met juste des valeurs
        // $this->taxes = $this->subtotal * 0.20; // Exemple de taxe globale
        // $this->total_amount = $this->subtotal + $this->taxes - $this->discount_amount + $this->shipping_cost;

        // Version plus précise si line_total est bien calculé
        $calculated_subtotal_from_lines = $this->items->sum(function($item) {
            // Somme des (quantité * prix unitaire) avant remises/taxes de ligne
            return (float)$item->quantity * (float)$item->unit_price; // Conversion explicite en float
        });
        $calculated_total_from_lines = (float)$this->items->sum('line_total'); // Conversion explicite en float

        $this->subtotal = $calculated_subtotal_from_lines; // Sous-total brut des lignes
        // Les taxes et remises globales s'appliquent après
        $this->total_amount = $calculated_total_from_lines - (float)$this->discount_amount + (float)$this->shipping_cost; // Conversion explicite en float
        // La variable $this->taxes pourrait être la somme des taxes des lignes OU une taxe globale supplémentaire

        $this->saveQuietly(); // Sauvegarder sans déclencher d'événements pour éviter les boucles
    }
}