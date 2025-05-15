<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments_received', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('payment_reference')->unique()->comment('Référence unique du paiement');
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('invoice_id')->nullable()->constrained('invoices')->nullOnDelete()->comment('Facture principale concernée, peut être null si paiement global');

            $table->date('payment_date');
            $table->decimal('amount', 15, 2);
            $table->string('payment_method')->comment('Ex: Virement, Chèque, CB, Espèces');
            $table->string('transaction_id')->nullable()->comment('ID de transaction du prestataire de paiement');
            $table->text('notes')->nullable();

            $table->string('user_id')->comment('Utilisateur qui a enregistré le paiement');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments_received');
    }
};
