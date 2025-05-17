<?php

namespace App\Models;

use Spatie\Activitylog\Models\Activity as SpatieActivity;

class ActivityLog extends SpatieActivity
{
    /**
     * Indique que les clés primaires ne sont pas auto-incrémentées.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Indique le type de la clé primaire.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Convertit les attributs lors de la récupération.
     *
     * @var array
     */
    protected $casts = [
        'properties' => 'collection',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
