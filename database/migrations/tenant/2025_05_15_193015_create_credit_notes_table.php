<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('credit_note_number')->unique();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('invoice_id')->nullable()->constrained('invoices')->nullOnDelete()->comment('Facture d\'origine si l\'avoir s\'y réfère');
            $table->string('user_id')->comment('Utilisateur qui a créé');

            $table->date('credit_note_date');
            $table->string('reason')->nullable()->comment('Raison de l\'avoir (ex: Retour marchandise, Erreur facture, Geste commercial)');
            $table->string('status')->default('draft')->comment('draft, issued, applied, voided');

            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('taxes_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0)->comment('Montant total de l\'avoir (positif)');

            $table->boolean('restock_items')->default(false)->comment('Indique si les items doivent être remis en stock');
            $table->text('internal_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
    }
};
