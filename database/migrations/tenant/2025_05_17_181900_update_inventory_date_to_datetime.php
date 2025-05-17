<?php

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
        Schema::table('inventory_sessions', function (Blueprint $table) {
            // Modifier le type de la colonne inventory_date de date à dateTime
            $table->dateTime('inventory_date')->comment('Date et heure de l\'inventaire')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_sessions', function (Blueprint $table) {
            // Revenir à une colonne date si nécessaire
            $table->date('inventory_date')->comment('Date de l\'inventaire')->change();
        });
    }
};
