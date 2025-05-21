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
     * 
     * Ajoute la colonne tenant_id aux tables de gestion des emplacements
     * pour le support multi-tenant.
     */
    public function up(): void
    {
        // Ajout de la colonne tenant_id à la table warehouses
        Schema::table('warehouses', function (Blueprint $table) {
            $table->string('tenant_id')->nullable()->after('id');
            
            // Vérifier si la contrainte d'unicité sur le nom existe
            if (Schema::hasTable('warehouses')) {
                $indexExists = collect(DB::select("SELECT indexname FROM pg_indexes WHERE tablename = 'warehouses' AND indexname = 'warehouses_name_unique'"))->isNotEmpty();
                if ($indexExists) {
                    $table->dropUnique(['name']); // Supprime la contrainte d'unicité sur le nom
                }
            }
            
            // Ajoute une contrainte d'unicité sur tenant_id + name
            $table->unique(['tenant_id', 'name']);
            $table->index('tenant_id');
        });
        
        // Mise à jour des enregistrements existants avec l'ID du tenant actuel
        // Obtention de l'ID du tenant à partir de la connexion actuelle
        $tenantId = DB::connection()->getDatabaseName();
        $tenantId = preg_replace('/^tenant_/', '', $tenantId);
        DB::statement("UPDATE warehouses SET tenant_id = '" . $tenantId . "' WHERE tenant_id IS NULL");
        
        // Rendre la colonne tenant_id non nullable après avoir mis à jour les données
        Schema::table('warehouses', function (Blueprint $table) {
            $table->string('tenant_id')->nullable(false)->change();
        });
        
        // Ajout de la colonne tenant_id à la table locations
        Schema::table('locations', function (Blueprint $table) {
            $table->string('tenant_id')->nullable()->after('id');
            
            // Vérifier si la contrainte d'unicité sur le code-barres existe
            if (Schema::hasTable('locations')) {
                $indexExists = collect(DB::select("SELECT indexname FROM pg_indexes WHERE tablename = 'locations' AND indexname = 'locations_barcode_unique'"))->isNotEmpty();
                if ($indexExists) {
                    $table->dropUnique(['barcode']); // Supprime la contrainte d'unicité sur le code-barres
                }
            }
            
            // Ajoute une contrainte d'unicité sur tenant_id + barcode
            $table->unique(['tenant_id', 'barcode']);
            $table->index('tenant_id');
        });
        
        // Mise à jour des enregistrements existants avec l'ID du tenant actuel
        // Utilisation du même ID de tenant pour toutes les tables
        DB::statement("UPDATE locations SET tenant_id = '" . $tenantId . "' WHERE tenant_id IS NULL");
        
        // Rendre la colonne tenant_id non nullable après avoir mis à jour les données
        Schema::table('locations', function (Blueprint $table) {
            $table->string('tenant_id')->nullable(false)->change();
        });
        
        // Ajout de la colonne tenant_id à la table product_location_stocks
        Schema::table('product_location_stocks', function (Blueprint $table) {
            $table->string('tenant_id')->nullable()->after('id');
            $table->index('tenant_id');
        });
        
        // Mise à jour des enregistrements existants avec l'ID du tenant actuel
        DB::statement("UPDATE product_location_stocks SET tenant_id = '" . $tenantId . "' WHERE tenant_id IS NULL");
        
        // Rendre la colonne tenant_id non nullable après avoir mis à jour les données
        Schema::table('product_location_stocks', function (Blueprint $table) {
            $table->string('tenant_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('location_management_tables', function (Blueprint $table) {
            //
        });
    }
};
