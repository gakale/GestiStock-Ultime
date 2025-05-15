<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'type',
        'company_name',
        'first_name',
        'last_name',
        'email',
        'phone_number',
        'vat_number',
        'billing_address_line1',
        'billing_address_line2',
        'billing_city',
        'billing_state_province',
        'billing_postal_code',
        'billing_country',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the client's full name or company name.
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->type === 'company' && !empty($this->company_name)) {
            return $this->company_name;
        }
        return trim($this->first_name . ' ' . $this->last_name);
    }

    // Plus tard, on ajoutera des relations : commandes, adresses multiples, etc.
    // public function orders(): HasMany
    // {
    //     return $this->hasMany(Order::class);
    // }

    // public function shippingAddresses(): HasMany
    // {
    //     return $this->hasMany(ClientShippingAddress::class);
    // }
}