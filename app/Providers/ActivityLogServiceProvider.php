<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\ActivityLog;
use App\Observers\ActivityLogObserver;
use Illuminate\Console\Scheduling\Schedule;

class ActivityLogServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Enregistrer l'observateur pour les logs d'activité
        ActivityLog::observe(ActivityLogObserver::class);
        
        // Planifier la tâche de nettoyage des logs d'activité
        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);
            $schedule->command('activity:prune')
                ->weekly()
                ->sundays()
                ->at('01:00')
                ->onOneServer();
        });
    }
}
