<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class CreditNote extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'credit_note_number', 'client_id', 'invoice_id', 'user_id',
        'credit_note_date', 'reason', 'status',
        'subtotal', 'taxes_amount', 'total_amount', // Pas de discount global ou shipping sur un avoir simple
        'restock_items', 'internal_notes',
    ];

    protected $casts = [
        'credit_note_date' => 'date',
        'subtotal' => 'decimal:2',
        'taxes_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'restock_items' => 'boolean',
    ];

    public static function generateNextCreditNoteNumber(): string
    {
        $prefix = 'CN-' . Carbon::now()->format('Ym') . '-'; // CN pour Credit Note
        // ... (logique de génération similaire)
        $lastCN = self::where('credit_note_number', 'like', $prefix . '%')->orderBy('credit_note_number', 'desc')->first();
        $nextNumber = 1;
        if ($lastCN) {
            $lastSequentialPart = substr($lastCN->credit_note_number, strlen($prefix));
            if (is_numeric($lastSequentialPart)) $nextNumber = (int)$lastSequentialPart + 1;
        }
        return $prefix . str_pad((string)$nextNumber, 4, '0', STR_PAD_LEFT);
    }

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($cn) {
            if (empty($cn->credit_note_number)) {
                $cn->credit_note_number = self::generateNextCreditNoteNumber();
            }
            if (empty($cn->user_id) && auth()->check() && auth()->user() instanceof TenantUser) {
                $cn->user_id = auth()->user()->getKey();
            }
            if (empty($cn->credit_note_date)) {
                $cn->credit_note_date = now()->toDateString();
            }
        });
        
        // Calculer les totaux après la sauvegarde (création ou mise à jour)
        static::saved(function ($cn) {
            // Éviter les boucles infinies en vérifiant si les totaux ont déjà été calculés
            if ($cn->wasChanged(['subtotal', 'taxes_amount', 'total_amount'])) {
                return; // Éviter la récursion si les totaux viennent d'être mis à jour
            }
            
            // Utiliser une propriété statique pour éviter les boucles infinies
            static $processing = [];
            
            // Vérifier si nous sommes déjà en train de traiter cet enregistrement
            if (!isset($processing[$cn->id])) {
                $processing[$cn->id] = true;
                $cn->calculateTotals();
                unset($processing[$cn->id]); // Libérer la mémoire
            }
        });
    }

    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); } // Facture d'origine
    public function user(): BelongsTo { return $this->belongsTo(TenantUser::class, 'user_id'); }
    public function items(): HasMany { return $this->hasMany(CreditNoteItem::class); }

    public function calculateTotals() // Similaire à Invoice/Quotation
    {
        // Utiliser fresh() pour éviter les problèmes de cache
        $creditNote = $this->fresh(['items']);
        
        $subtotal = 0;
        $totalTaxes = 0;
        
        if ($creditNote && $creditNote->items->count() > 0) {
            foreach ($creditNote->items as $item) {
                // Convertir explicitement en float pour éviter les problèmes de type
                $quantity = (float) $item->quantity;
                $unitPrice = (float) $item->unit_price;
                $discountPercentage = (float) $item->discount_percentage;
                $taxRate = (float) $item->tax_rate;
                
                $lineBaseForTax = ($quantity * $unitPrice) * (1 - ($discountPercentage / 100));
                $subtotal += $lineBaseForTax;
                $totalTaxes += $lineBaseForTax * ($taxRate / 100);
            }
        }
        
        // Arrondir à 2 décimales pour éviter les erreurs de précision
        $subtotal = round($subtotal, 2);
        $totalTaxes = round($totalTaxes, 2);
        $totalAmount = round($subtotal + $totalTaxes, 2);
        
        // Enregistrer les anciennes valeurs pour le log
        $oldSubtotal = $this->subtotal;
        $oldTaxes = $this->taxes_amount;
        $oldTotal = $this->total_amount;
        
        // Mettre à jour les valeurs
        $this->subtotal = $subtotal;
        $this->taxes_amount = $totalTaxes;
        $this->total_amount = $totalAmount;
        
        // Journaliser les modifications pour le débogage
        \Illuminate\Support\Facades\Log::info("[CreditNote] Totaux recalculés pour l'avoir #{$this->credit_note_number} (ID: {$this->id}):", [
            'items_count' => $creditNote ? $creditNote->items->count() : 0,
            'old_subtotal' => $oldSubtotal,
            'new_subtotal' => $subtotal,
            'old_taxes' => $oldTaxes,
            'new_taxes' => $totalTaxes,
            'old_total' => $oldTotal,
            'new_total' => $totalAmount
        ]);
        
        // Utiliser les attributs du modèle uniquement pour les colonnes qui existent réellement
        $attributes = [
            'subtotal' => $subtotal,
            'taxes_amount' => $totalTaxes,
            'total_amount' => $totalAmount,
        ];
        
        // Sauvegarder sans déclencher les événements pour éviter les boucles infinies
        $this->newQuery()->where('id', $this->id)->update($attributes);
        
        // Rafraîchir le modèle pour avoir les valeurs à jour
        $this->refresh();
        
        return $this;
    }
}