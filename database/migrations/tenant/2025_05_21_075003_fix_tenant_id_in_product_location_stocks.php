<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Rendre la colonne tenant_id nullable temporairement
        Schema::table('product_location_stocks', function (Blueprint $table) {
            $table->string('tenant_id')->nullable()->change();
        });
        
        // 2. Mettre à jour les enregistrements existants sans tenant_id
        $tenantId = null;
        try {
            // Obtenir l'ID du tenant à partir du nom de la base de données
            $currentDb = DB::connection()->getDatabaseName();
            if (str_starts_with($currentDb, 'tenant_')) {
                $tenantId = str_replace('tenant_', '', $currentDb);
            }
        } catch (\Exception $e) {
            // Gérer l'erreur silencieusement
        }
        
        if ($tenantId) {
            DB::statement("UPDATE product_location_stocks SET tenant_id = '" . $tenantId . "' WHERE tenant_id IS NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rétablir la contrainte NOT NULL sur tenant_id
        Schema::table('product_location_stocks', function (Blueprint $table) {
            $table->string('tenant_id')->nullable(false)->change();
        });
    }
};
