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
        Schema::create('warehouses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->uuid('parent_location_id')->nullable(); // Pour la hiérarchie, on ajoutera la contrainte après
            $table->string('name'); // Ex: "Zone A", "Allée 01-A", "Étagère 3", "Casier B02"
            $table->string('barcode')->nullable()->unique();
            $table->string('location_type')->nullable()->comment('Ex: receiving, storage, picking, shipping_dock, zone, aisle, shelf, bin');
            $table->boolean('is_pickable')->default(true)->comment('Peut-on prélever du stock depuis cet emplacement ?');
            $table->boolean('is_storable')->default(true)->comment('Peut-on stocker des marchandises dans cet emplacement ?');
            $table->integer('sequence')->default(0)->comment('Pour l\'ordre de picking/rangement');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['warehouse_id', 'name']); // Nom d'emplacement unique par entrepôt
        });
        
        // Ajout de la contrainte de clé étrangère après la création de la table
        Schema::table('locations', function (Blueprint $table) {
            $table->foreign('parent_location_id')
                  ->references('id')
                  ->on('locations')
                  ->onDelete('cascade');
        });

        Schema::create('product_location_stocks', function (Blueprint $table) {
            $table->uuid('id')->primary(); // Ou clé primaire composite si préférée et gérée
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUuid('location_id')->constrained('locations')->cascadeOnDelete();
            $table->decimal('quantity', 15, 2)->default(0); // Quantité dans l'unité de stock du produit
            // $table->foreignUuid('unit_of_measure_id'); // Normalement, c'est l'unité de stock du produit
            $table->timestamps(); // Pour savoir quand ce niveau de stock a été mis à jour

            $table->unique(['product_id', 'location_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('location_management_tables');
    }
};
