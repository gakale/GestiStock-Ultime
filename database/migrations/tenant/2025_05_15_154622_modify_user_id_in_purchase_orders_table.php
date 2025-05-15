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
        // Supprimer la colonne user_id existante
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });

        // Ajouter une nouvelle colonne user_id qui accepte à la fois les UUID et les entiers
        Schema::table('purchase_orders', function (Blueprint $table) {
            // Utiliser string au lieu de uuid pour accepter à la fois les UUID et les entiers
            $table->string('user_id')->comment('Utilisateur qui a créé la commande');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Supprimer la colonne user_id de type string
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });

        // Rétablir la colonne user_id de type uuid
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->uuid('user_id')->comment('Utilisateur qui a créé la commande');
        });
    }
};
