<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_number')->unique()->comment('Numéro de Bon de Commande unique');
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('user_id')->comment('Utilisateur qui a créé');

            $table->foreignUuid('quotation_id')->nullable()->constrained('quotations')->nullOnDelete(); // Si généré depuis un devis

            $table->date('order_date');
            $table->date('expected_shipment_date')->nullable()->comment('Date d\'expédition prévue');
            $table->string('status')->default('pending_confirmation')
                  ->comment('pending_confirmation, confirmed, partially_shipped, fully_shipped, invoiced, completed, cancelled');

            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('taxes_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('shipping_charges', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);

            $table->string('client_po_reference')->nullable()->comment('Référence commande du client');
            $table->text('shipping_address_details')->nullable()->comment('Détails adresse livraison (peut être copié du client)');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_orders');
    }
};
