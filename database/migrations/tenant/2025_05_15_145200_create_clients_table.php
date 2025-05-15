<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type')->default('individual')->comment('individual, company'); // Particulier ou Entreprise
            $table->string('company_name')->nullable(); // Si type='company'
            $table->string('first_name')->nullable();   // Nom si particulier, ou contact principal si entreprise
            $table->string('last_name');                // Prénom si particulier, ou nom du contact
            $table->string('email')->nullable()->unique();
            $table->string('phone_number')->nullable();
            $table->string('vat_number')->nullable()->comment('Numéro de TVA');

            // Adresse de facturation principale
            $table->string('billing_address_line1')->nullable();
            $table->string('billing_address_line2')->nullable();
            $table->string('billing_city')->nullable();
            $table->string('billing_state_province')->nullable(); // État / Province
            $table->string('billing_postal_code')->nullable();
            $table->string('billing_country')->nullable(); // Code pays ISO ?

            // Notes internes
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
