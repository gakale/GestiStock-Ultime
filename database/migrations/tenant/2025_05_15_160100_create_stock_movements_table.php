<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete(); // Si le produit est supprimé, ses mouvements aussi
            $table->string('type')->comment('Ex: purchase_receipt, sale, inventory_adjustment_positive, inventory_adjustment_negative, stock_transfer_out, stock_transfer_in, customer_return, supplier_return');
            $table->integer('quantity_changed')->comment('Positive pour entrée, Négative pour sortie');
            $table->integer('new_stock_quantity_after_movement'); // Le stock du produit après ce mouvement
            $table->dateTime('movement_date'); // Date et heure du mouvement

            $table->string('related_document_type')->nullable()->comment('Ex: App\Models\GoodsReceipt, App\Models\Invoice, App\Models\InventoryAdjustment');
            $table->uuid('related_document_id')->nullable(); // ID du document lié (type string pour flexibilité si certains ID ne sont pas UUID)
            // Index pour la recherche polymorphique sur le document lié
            $table->index(['related_document_type', 'related_document_id'], 'related_document_index');

            $table->string('user_id')->nullable()->comment('Utilisateur responsable du mouvement'); // String pour compatibilité avec notre solution UUID/string
            $table->text('reason')->nullable()->comment('Raison du mouvement, surtout pour les ajustements');
            $table->text('notes')->nullable();
            $table->timestamps(); // created_at sera la date d'enregistrement du mouvement
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
