<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Vérifie si la table users existe déjà
        if (Schema::hasTable('users')) {
            // Vérifie si la migration est déjà enregistrée
            $migrationExists = DB::table('migrations')
                ->where('migration', '2025_05_15_131011_create_tenant_users_table')
                ->exists();
                
            // Si la migration n'est pas encore enregistrée, l'ajouter
            if (!$migrationExists) {
                DB::table('migrations')->insert([
                    'migration' => '2025_05_15_131011_create_tenant_users_table',
                    'batch' => 1
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Optionnel: supprimer l'entrée de migration si nécessaire
        DB::table('migrations')
            ->where('migration', '2025_05_15_131011_create_tenant_users_table')
            ->delete();
    }
};
