<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            // Ajout des colonnes pour les emplacements source et destination
            $table->foreignUuid('source_location_id')->nullable()->after('product_id')
                  ->constrained('locations')->nullOnDelete()
                  ->comment('Emplacement source du mouvement (pour les sorties et transferts)');
                  
            $table->foreignUuid('destination_location_id')->nullable()->after('source_location_id')
                  ->constrained('locations')->nullOnDelete()
                  ->comment('Emplacement destination du mouvement (pour les entrées et transferts)');
                  
            // Ajout de tenant_id pour la gestion multi-tenant
            $table->string('tenant_id')->nullable()->after('id');
            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            // Suppression des clés étrangères
            $table->dropForeign(['source_location_id']);
            $table->dropForeign(['destination_location_id']);
            
            // Suppression des colonnes
            $table->dropColumn(['source_location_id', 'destination_location_id', 'tenant_id']);
        });
    }
};
