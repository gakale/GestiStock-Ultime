<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('company_name');
            $table->string('contact_first_name')->nullable();
            $table->string('contact_last_name')->nullable();
            $table->string('email')->nullable()->unique();
            $table->string('phone_number')->nullable();
            $table->string('vat_number')->nullable()->comment('Numéro de TVA');

            // Adresse principale
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('state_province')->nullable(); // État / Province
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();

            // Conditions fournisseur
            $table->string('payment_terms')->nullable()->comment('Ex: Net 30 jours');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
