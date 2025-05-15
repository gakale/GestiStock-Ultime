<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('invoice_number')->unique()->comment('Numéro de facture unique');
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('user_id')->comment('Utilisateur qui a créé la facture'); // String pour compatibilité avec notre solution UUID/string

            $table->date('invoice_date'); // Date d'émission de la facture
            $table->date('due_date');     // Date d'échéance
            $table->string('status')->default('draft')
                  ->comment('draft, sent, partially_paid, paid, overdue, voided, cancelled');

            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('taxes_amount', 15, 2)->default(0); // Montant total des taxes
            $table->decimal('discount_amount', 15, 2)->default(0); // Montant de la remise globale
            $table->decimal('shipping_charges', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0); // Montant déjà payé

            $table->string('payment_terms')->nullable(); // Ex: "Paiement à 30 jours net"
            $table->text('notes_to_client')->nullable();
            $table->text('internal_notes')->nullable();

            // Champs pour le suivi si la facture est issue d'un autre document
            // $table->string('source_document_type')->nullable(); // Ex: App\Models\SalesOrder
            // $table->uuid('source_document_id')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
