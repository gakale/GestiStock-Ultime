<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_number')->unique()->comment('Numéro de commande unique');
            $table->foreignUuid('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->uuid('user_id')->comment('Utilisateur qui a créé la commande'); // Utilisateur du tenant
            // Nous n'utilisons pas foreignUuid ici car il pourrait y avoir une incompatibilité de types
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->string('status')->default('draft')
                  ->comment('draft, pending_approval, approved, ordered, partially_received, fully_received, cancelled');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('taxes', 15, 2)->default(0);     // Montant total des taxes
            $table->decimal('discount_amount', 15, 2)->default(0); // Montant de la remise globale
            $table->decimal('shipping_cost', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->text('supplier_reference')->nullable()->comment('Référence commande chez le fournisseur');
            $table->text('notes_to_supplier')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
