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
        // Création de la table sans la contrainte de clé étrangère auto-référentielle
        Schema::create('unit_of_measures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique()->comment('Nom complet de l\'unité (ex: Pièce, Carton de 12, Mètre Linéaire)');
            $table->string('symbol')->unique()->comment('Symbole court (ex: pce, ctn12, m, kg)');
            $table->string('type')->default('countable')->comment('Type d\'unité: countable, weight, length, volume, other'); // Pour regroupement/logique future

            // Relation de conversion par rapport à une unité de base pour ce type d'unité
            // Par exemple, "Carton de 12" (symbol: ctn12) a comme unité de base "Pièce" (symbol: pce) avec un facteur de 12.
            // "Pièce" est une unité de base pour elle-même (base_unit_id=null, conversion_factor=1).
            $table->uuid('base_unit_id')->nullable()->comment('Unité de base pour la conversion (si cette unité n\'est pas elle-même une base)');
            $table->decimal('conversion_factor', 15, 5)->default(1.00000)->comment('Facteur pour convertir CETTE unité en son unité DE BASE (ex: 1 Carton = 12 Pièces -> factor=12 si Pièce est la base)');
            // Si Pièce est la base pour Carton, et que Carton est l'unité actuelle:
            // Quantité en Unité de Base = Quantité en Carton * conversion_factor
            // Quantité en Carton = Quantité en Unité de Base / conversion_factor

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        
        // Ajout de la contrainte de clé étrangère après la création de la table
        Schema::table('unit_of_measures', function (Blueprint $table) {
            $table->foreign('base_unit_id')
                  ->references('id')
                  ->on('unit_of_measures')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_of_measures');
    }
};
