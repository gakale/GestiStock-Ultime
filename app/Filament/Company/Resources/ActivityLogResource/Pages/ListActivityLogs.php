<?php

namespace App\Filament\Company\Resources\ActivityLogResource\Pages;

use App\Filament\Company\Resources\ActivityLogResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListActivityLogs extends ListRecords
{
    protected static string $resource = ActivityLogResource::class;
    
    public function makeFilamentTranslatableContentDriver(): ?\Filament\Support\Contracts\TranslatableContentDriver
    {
        return null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('purge')
                ->label('Purger les anciens logs')
                ->color('danger')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->modalHeading('Purger les anciens logs d\'activité')
                ->modalDescription('Cette action supprimera tous les logs d\'activité datant de plus de 365 jours. Cette action est irréversible.')
                ->modalSubmitActionLabel('Purger')
                ->action(function () {
                    $daysToKeep = config('activitylog.delete_records_older_than_days', 365);
                    $purgeDate = now()->subDays($daysToKeep);
                    
                    $deletedCount = \Spatie\Activitylog\Models\Activity::where('created_at', '<', $purgeDate)->delete();
                    
                    $this->notification()->success(
                        title: 'Logs purgés',
                        body: "{$deletedCount} logs d'activité ont été supprimés."
                    );
                }),
        ];
    }
}
