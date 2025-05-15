<?php

namespace App\Models; // Ou App\Models\Company si vous avez mis User.php dans un sous-dossier Company

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\FilamentUser; // Important pour Filament
use Filament\Panel;                       // Important pour Filament

class TenantUser extends Authenticatable implements FilamentUser // Assurez-vous que la classe s'appelle bien TenantUser si le fichier est TenantUser.php
{
    use HasFactory, Notifiable, HasUuids; // REMETTRE HasUuids
    
    /**
     * La table associée au modèle.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * The connection name for the model.
     *
     * @var string|null
     */
    // protected $connection = 'tenant'; // Normalement pas nécessaire avec tenancyforlaravel v3,
                                      // car il change la connexion par défaut dynamiquement.

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Implémentation de FilamentUser
    public function canAccessPanel(Panel $panel): bool
    {
        // Vérifie si l'utilisateur peut accéder au panel spécifié.
        // Pour le panel 'company', nous voulons autoriser tous les TenantUser authentifiés.
        if ($panel->getId() === 'company') {
            return true;
        }
        // Vous pourriez avoir d'autres panels pour les tenants plus tard
        // et vouloir une logique différente ici.
        return false;
    }
}