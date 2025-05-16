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
        Schema::create('supplier_credit_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('credit_note_number')->unique()->comment('Numéro de l\'avoir fournisseur');
            $table->foreignUuid('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignUuid('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete()->comment('Commande fournisseur d\'origine si applicable');
            $table->foreignUuid('goods_receipt_id')->nullable()->constrained('goods_receipts')->nullOnDelete()->comment('Bon de réception d\'origine si applicable');
            $table->string('user_id')->comment('Utilisateur ayant créé l\'avoir');

            $table->date('credit_note_date')->comment('Date de l\'avoir');
            $table->text('reason')->nullable()->comment('Raison du retour/avoir');
            $table->string('status')->default('draft')->comment('Statut: draft, confirmed, cancelled');

            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('taxes_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);

            $table->boolean('items_returned_to_supplier_stock')->default(false)->comment('Indique si les articles ont physiquement quitté notre stock pour retourner chez le fournisseur');
            $table->text('internal_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Assurez-vous que cette contrainte pointe vers la bonne table utilisateurs de votre tenant
            // Modifié pour utiliser string au lieu de foreignUuid pour user_id
        });

        Schema::create('supplier_credit_note_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('supplier_credit_note_id')->constrained('supplier_credit_notes')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();

            $table->string('description')->nullable()->comment('Peut reprendre la description du produit ou être spécifique');
            $table->decimal('quantity', 15, 2);
            $table->string('unit')->nullable()->comment('Unité de mesure (ex: pièce, kg, L)');
            $table->decimal('unit_price', 15, 2)->comment('Prix unitaire au moment du retour/achat');

            $table->decimal('tax_rate', 5, 2)->default(0)->comment('Taux de TVA en % (ex: 20.00 pour 20%)');
            $table->decimal('tax_amount', 15, 2)->default(0)->comment('Montant de la TVA pour la ligne');
            $table->decimal('line_total', 15, 2)->comment('Total pour la ligne (qty * unit_price + tax_amount)');

            $table->timestamps();
            // Pas de SoftDeletes pour les items en général, sauf si besoin spécifique
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_credit_note_items');
        Schema::dropIfExists('supplier_credit_notes');
    }
};
