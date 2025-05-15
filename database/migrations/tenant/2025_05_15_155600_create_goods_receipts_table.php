<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('receipt_number')->unique()->comment('Numéro de bon de réception unique');
            $table->foreignUuid('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete(); // Lié à une commande fournisseur
            $table->foreignUuid('supplier_id')->constrained('suppliers')->cascadeOnDelete(); // Fournisseur (peut être redondant si purchase_order_id est là, mais utile si réception sans commande)
            $table->string('supplier_delivery_note_number')->nullable()->comment('Numéro du BL fournisseur');
            $table->date('receipt_date');
            $table->string('received_by_user_id')->comment('Utilisateur qui a réceptionné'); // Utilisateur du tenant (string pour compatibilité avec notre solution UUID)
            $table->string('status')->default('completed')->comment('completed, cancelled, pending_quality_check'); // Statut de la réception elle-même
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipts');
    }
};
