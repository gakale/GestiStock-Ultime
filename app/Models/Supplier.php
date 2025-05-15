<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'company_name',
        'contact_first_name',
        'contact_last_name',
        'email',
        'phone_number',
        'vat_number',
        'address_line1',
        'address_line2',
        'city',
        'state_province',
        'postal_code',
        'country',
        'payment_terms',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the supplier's contact full name if available, otherwise company name.
     */
    public function getDisplayNameAttribute(): string
    {
        if (!empty($this->contact_first_name) || !empty($this->contact_last_name)) {
            return trim($this->contact_first_name . ' ' . $this->contact_last_name) . ' (' . $this->company_name . ')';
        }
        return $this->company_name;
    }

    // Plus tard : commandes fournisseurs, produits fournis, etc.
    // public function purchaseOrders(): HasMany
    // {
    //     return $this->hasMany(PurchaseOrder::class);
    // }
}