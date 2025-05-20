<?php

namespace App\Policies;

use App\Models\CreditNote;
use App\Models\User;
use App\Models\TenantUser;
use Illuminate\Auth\Access\Response;

class CreditNotePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(TenantUser $user): bool
    {
        // Autoriser tous les utilisateurs à voir la liste des avoirs
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(TenantUser $user, CreditNote $creditNote): bool
    {
        // Autoriser tous les utilisateurs à voir les détails d'un avoir
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(TenantUser $user): bool
    {
        // Autoriser tous les utilisateurs à créer des avoirs
        return true;
    }

    /**
     * Determine whether the user can update the model.
     * 
     * @param TenantUser $user
     * @param CreditNote|null $creditNote
     * @return bool
     */
    public function update(TenantUser $user, ?CreditNote $creditNote = null): bool
    {
        // Si aucun modèle spécifique n'est fourni (cas des actions en masse)
        if ($creditNote === null) {
            // Autoriser la mise à jour en masse pour tous les utilisateurs
            return true;
        }
        
        // Autoriser la modification pour les avoirs en brouillon ou émis
        // Cela permet de corriger les avoirs même après leur émission
        return in_array($creditNote->status, ['draft', 'issued']);
    }

    /**
     * Determine whether the user can delete the model.
     * 
     * @param TenantUser $user
     * @param CreditNote|null $creditNote
     * @return bool
     */
    public function delete(TenantUser $user, ?CreditNote $creditNote = null): bool
    {
        // Si aucun modèle spécifique n'est fourni (cas des actions en masse)
        if ($creditNote === null) {
            // Autoriser la suppression en masse pour tous les utilisateurs
            return true;
        }
        
        // Autoriser la suppression uniquement pour les avoirs en brouillon
        return $creditNote->status === 'draft';
    }

    /**
     * Determine whether the user can restore the model.
     * 
     * @param TenantUser $user
     * @param CreditNote|null $creditNote
     * @return bool
     */
    public function restore(TenantUser $user, ?CreditNote $creditNote = null): bool
    {
        // Autoriser la restauration pour tous les utilisateurs
        return true;
    }

    /**
     * Determine whether the user can permanently delete the model.
     * 
     * @param TenantUser $user
     * @param CreditNote|null $creditNote
     * @return bool
     */
    public function forceDelete(TenantUser $user, ?CreditNote $creditNote = null): bool
    {
        // Si aucun modèle spécifique n'est fourni (cas des actions en masse)
        if ($creditNote === null) {
            // Autoriser la suppression définitive en masse uniquement pour les administrateurs
            return $user->is_admin;
        }
        
        // Autoriser la suppression définitive uniquement pour les administrateurs
        // Vous pouvez adapter cette logique selon vos besoins
        return $user->is_admin;
    }
}
