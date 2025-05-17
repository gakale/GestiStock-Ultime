<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Models\Traits\FormatsActivityLogEvents;

class Client extends Model
{
    use HasFactory, HasUuids, SoftDeletes, LogsActivity, FormatsActivityLogEvents;

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

    /**
     * Relation avec les factures du client
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
    
    /**
     * Calcule le solde dû par le client (factures non payées)
     */
    protected function balanceDue(): Attribute
    {
        return Attribute::make(
            get: function () {
                // Somme de (total_amount - amount_paid) pour les factures non complètement payées et non annulées
                $dueAmount = $this->invoices()
                    ->whereNotIn('status', ['paid', 'cancelled']) // Exclure les factures payées ou annulées
                    ->sum(DB::raw('total_amount - amount_paid')); // Calcule le solde pour chaque facture et somme

                return (float) $dueAmount;
            }
        );
    }

    /**
     * Calcule le chiffre d'affaires total généré par ce client
     */
    protected function totalRevenue(): Attribute
    {
        return Attribute::make(
            get: fn () => (float) $this->invoices()
                                ->whereIn('status', ['paid', 'issued', 'partially_paid']) // Factures contribuant au CA
                                ->sum('total_amount')
        );
    }

    // public function shippingAddresses(): HasMany
    // {
    //     return $this->hasMany(ClientShippingAddress::class);
    // }

    /**
     * Configuration des logs d'activité
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'company_name', 'first_name', 'last_name', 'email',
                'phone_number', 'billing_address_line1', 'billing_city', 'is_active'
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Le client '{$this->getDisplayNameAttribute()}' a été {$this->formatEventName($eventName)}.")
            ->useLogName('client_activity');
    }
}