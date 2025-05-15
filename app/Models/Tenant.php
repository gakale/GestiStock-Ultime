<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Illuminate\Support\Str;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains, HasFactory, HasUuids;

    protected $fillable = [
        'id', // Important pour les UUIDs si vous ne voulez pas de `tenancy_id`
        'name',
        'slug',
        'data', // Colonne JSON pour des métadonnées
        'ready', // Pourrait être utilisé pour indiquer si le tenant est pleinement configuré
        // Ajoutez d'autres champs spécifiques à la gestion centrale des tenants ici
    ];

    protected $casts = [
        'data' => 'array',
        'ready' => 'boolean',
    ];

    public static function getCustomColumns(): array
    {
        return [
            'id', // Important pour que tenancy utilise notre champ 'id' comme clé primaire
            'name',
            'slug',
            'data',
            'ready',
        ];
    }

    // Méthode pour s'assurer que 'id' est utilisé comme clé primaire par le package
    public function getTenantKeyName(): string
    {
        return 'id';
    }

    public function getTenantKey()
    {
        return $this->getAttribute($this->getTenantKeyName());
    }

    // Générer un slug automatiquement lors de la création si non fourni
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tenant) {
            if (empty($tenant->slug)) {
                $tenant->slug = Str::slug($tenant->name);
            }
            // S'assurer que l'ID est généré si HasUuids est utilisé
            if (empty($tenant->id)) {
                $tenant->id = (string) Str::uuid();
            }
        });

        static::updating(function ($tenant) {
            if ($tenant->isDirty('name') && !$tenant->isDirty('slug')) {
                 $tenant->slug = Str::slug($tenant->name);
            }
        });
    }
}