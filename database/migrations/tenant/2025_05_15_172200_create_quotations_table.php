<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('quotation_number')->unique();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('user_id')->comment('Utilisateur qui a créé le devis'); // String pour compatibilité avec notre solution UUID/string

            $table->date('quotation_date'); // Date d'émission du devis
            $table->date('expiry_date')->nullable(); // Date d'expiration de l'offre
            $table->string('status')->default('draft')
                  ->comment('draft, sent, accepted, declined, expired');

            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('taxes_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0); // Remise globale
            $table->decimal('shipping_charges', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);

            $table->text('terms_and_conditions')->nullable();
            $table->text('notes_to_client')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamps();
            $table->softDeletes(); // Si on veut pouvoir "archiver" les devis
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
