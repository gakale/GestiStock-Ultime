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
        Schema::create('inventory_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference_number')->unique()->comment('Référence unique de la session d\'inventaire');
            $table->date('inventory_date')->comment('Date de l\'inventaire');
            $table->string('status')->default('draft')->comment('Statut: draft, in_progress, completed, validated, cancelled');
            // 'draft': Préparation, sélection des produits
            // 'in_progress': Comptage en cours
            // 'completed': Comptage terminé, en attente de validation des écarts
            // 'validated': Écarts validés, stock mis à jour
            // 'cancelled': Session annulée
            $table->string('user_id')->comment('Utilisateur responsable'); // Référence à la table users du tenant
            $table->text('notes')->nullable()->comment('Notes générales sur la session d\'inventaire');
            // Plus tard, on pourrait ajouter:
            // $table->foreignUuid('warehouse_id')->nullable()->comment('Entrepôt concerné');
            // $table->string('inventory_type')->default('full')->comment('Type: full, partial, cycle_count');
            $table->timestamps();
            $table->softDeletes();

            // La contrainte de clé étrangère est commentée pour éviter les problèmes de type
            // $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('inventory_session_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('inventory_session_id')->constrained('inventory_sessions')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();

            $table->decimal('theoretical_quantity', 15, 2)->comment('Quantité en stock théorique au moment du lancement');
            $table->decimal('counted_quantity', 15, 2)->nullable()->comment('Quantité réellement comptée');
            $table->decimal('difference_quantity', 15, 2)->storedAs('counted_quantity - theoretical_quantity')->nullable()->comment('Écart: compté - théorique (colonne calculée)');
            // StoredAs est supporté par MySQL 5.7+ et PostgreSQL. Pour SQLite, il faut le calculer en PHP.
            // Si StoredAs n'est pas supporté ou voulu, on le calculera dans le modèle.

            // $table->string('batch_number')->nullable(); // Pour gestion par lots
            // $table->date('expiry_date')->nullable(); // Pour gestion par dates de péremption
            $table->text('item_notes')->nullable()->comment('Notes spécifiques à cet item d\'inventaire');
            $table->timestamps();

            $table->unique(['inventory_session_id', 'product_id']); // Un produit ne peut être qu'une fois par session d'inventaire
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_session_items');
        Schema::dropIfExists('inventory_sessions');
    }
};
