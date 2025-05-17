<?php

namespace App\Models\Traits;

trait FormatsActivityLogEvents
{
    protected function formatEventName(string $eventName): string
    {
        return match ($eventName) {
            'created' => 'créé(e)',
            'updated' => 'mis(e) à jour',
            'deleted' => 'supprimé(e)',
            'restored' => 'restauré(e)',
            default => $eventName,
        };
    }
}
