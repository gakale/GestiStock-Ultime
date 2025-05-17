<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ActivityLog;

class PruneActivityLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'activity:prune {--days=365 : Nombre de jours à conserver}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Supprime les logs d\'activité plus anciens que le nombre de jours spécifié';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = $this->option('days') ?: config('activitylog.delete_records_older_than_days', 365);
        $purgeDate = now()->subDays($days);
        
        $this->info("Suppression des logs d'activité antérieurs au {$purgeDate->format('d/m/Y')}...");
        
        $count = ActivityLog::where('created_at', '<', $purgeDate)->delete();
        
        $this->info("{$count} logs d'activité ont été supprimés.");
        
        return Command::SUCCESS;
    }
}
