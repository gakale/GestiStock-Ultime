<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class InventorySession extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'reference_number',
        'inventory_date',
        'status',
        'user_id',
        'notes',
        // 'warehouse_id',
        // 'inventory_type',
    ];

    protected $casts = [
        'inventory_date' => 'datetime',
    ];

    public static function generateNextReferenceNumber(): string
    {
        $prefix = 'INVTRY-' . Carbon::now()->format('Ym') . '-'; // INVTRY pour Inventory
        $lastSession = self::where('reference_number', 'like', $prefix . '%')
                            ->orderBy('reference_number', 'desc')
                            ->first();
        $nextNumber = 1;
        if ($lastSession) {
            $lastSequentialPart = substr($lastSession->reference_number, strlen($prefix));
            if (is_numeric($lastSequentialPart)) {
                $nextNumber = (int)$lastSequentialPart + 1;
            }
        }
        return $prefix . str_pad((string)$nextNumber, 4, '0', STR_PAD_LEFT);
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($session) {
            if (empty($session->reference_number)) {
                $session->reference_number = self::generateNextReferenceNumber();
            }
            if (empty($session->user_id) && auth()->check() && auth()->user() instanceof TenantUser) {
                $session->user_id = auth()->user()->getKey();
            }
            if (empty($session->inventory_date)) {
                $session->inventory_date = now()->toDateString();
            }
            if (empty($session->status)) {
                $session->status = 'draft';
            }
        });
    }

    public function user(): BelongsTo // Utilisateur du tenant
    {
        return $this->belongsTo(TenantUser::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventorySessionItem::class);
    }

    // Méthode pour calculer le nombre d'items, d'items comptés, etc. si nécessaire pour affichage.
    public function getTotalItemsAttribute(): int
    {
        return $this->items()->count();
    }

    public function getItemsCountedAttribute(): int
    {
        return $this->items()->whereNotNull('counted_quantity')->count();
    }
}