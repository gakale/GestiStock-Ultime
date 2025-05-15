<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CustomizeTenantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Ajoute la colonne name si elle n'existe pas déjà
            if (!Schema::hasColumn('tenants', 'name')) {
                $table->string('name')->after('id');
            }
            
            // Ajoute la colonne slug si elle n'existe pas déjà
            if (!Schema::hasColumn('tenants', 'slug')) {
                $table->string('slug')->unique()->after('name');
            }
            
            // Ajoute la colonne ready si elle n'existe pas déjà
            if (!Schema::hasColumn('tenants', 'ready')) {
                $table->boolean('ready')->default(false);
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Supprime les colonnes personnalisées si elles existent
            $columns = ['slug', 'name', 'ready'];
            $existingColumns = [];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('tenants', $column)) {
                    $existingColumns[] = $column;
                }
            }
            
            if (!empty($existingColumns)) {
                $table->dropColumn($existingColumns);
            }
        });
    }
}
