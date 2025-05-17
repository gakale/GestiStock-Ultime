<?php

namespace App\Observers;

use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Facades\Auth;

class ActivityLogObserver
{
    /**
     * Handle the Activity "creating" event.
     */
    public function creating(Activity $activity): void
    {
        // Si l'utilisateur est authentifié, associer son ID à l'activité
        if (Auth::check()) {
            $activity->causer_id = Auth::id();
            $activity->causer_type = get_class(Auth::user());
        }

        // Enrichir les propriétés avec des informations supplémentaires
        $properties = $activity->properties->toArray();
        
        // Ajouter l'IP de l'utilisateur
        $properties['ip_address'] = request()->ip();
        
        // Ajouter l'agent utilisateur
        $properties['user_agent'] = request()->userAgent();
        
        // Ajouter la date et l'heure formatées
        $properties['formatted_date'] = now()->format('d/m/Y H:i:s');
        
        // Mettre à jour les propriétés
        $activity->properties = $properties;
    }
}
