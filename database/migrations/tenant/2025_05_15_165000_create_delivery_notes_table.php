<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('delivery_note_number')->unique();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('user_id')->comment('Utilisateur qui a traité la livraison'); // String pour compatibilité avec notre solution UUID/string

            // Peut être lié à une Facture ou à un Bon de Commande Client
            $table->foreignUuid('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            // $table->foreignUuid('sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete(); // À ajouter plus tard

            $table->date('delivery_date');
            $table->string('status')->default('draft') // draft, ready_to_ship, shipped, delivered, cancelled
                ->comment('draft, ready_to_ship, shipped, delivered, cancelled');

            $table->text('shipping_address_line1')->nullable();
            $table->text('shipping_address_line2')->nullable();
            $table->string('shipping_city')->nullable();
            $table->string('shipping_postal_code')->nullable();
            $table->string('shipping_country')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('carrier_name')->nullable(); // Nom du transporteur
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_notes');
    }
};
