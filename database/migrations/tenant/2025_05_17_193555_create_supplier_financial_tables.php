<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Table pour les factures reçues des fournisseurs
        Schema::create('supplier_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->comment('Utilisateur ayant encodé la facture'); // Utilisateur du tenant
            $table->foreignUuid('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete()->comment('Commande fournisseur liée');
            $table->foreignUuid('goods_receipt_id')->nullable()->constrained('goods_receipts')->nullOnDelete()->comment('Bon de réception lié');

            $table->string('supplier_invoice_number')->comment('Numéro de facture fourni par le fournisseur');
            $table->date('invoice_date')->comment('Date de la facture fournisseur');
            $table->date('due_date')->nullable()->comment('Date d\'\u00e9chéance de la facture fournisseur');
            $table->string('status')->default('pending')->comment('Statut: pending, partially_paid, paid, overdue, cancelled');

            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('taxes_amount', 15, 2)->default(0);
            // Pas de remise globale typiquement sur une facture fournisseur qu'on reçoit,
            // mais on pourrait ajouter si nécessaire. Les remises sont plutôt sur les lignes.
            $table->decimal('shipping_charges', 15, 2)->default(0)->comment('Frais de port facturés par le fournisseur');
            $table->decimal('total_amount', 15, 2)->default(0)->comment('Montant total de la facture fournisseur');
            $table->decimal('amount_paid', 15, 2)->default(0)->comment('Montant déjà payé pour cette facture');

            $table->text('notes')->nullable()->comment('Notes internes concernant cette facture');
            $table->string('attachment_path')->nullable()->comment('Chemin vers le scan/PDF de la facture originale');

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['supplier_id', 'supplier_invoice_number'], 'supplier_invoice_unique'); // Une facture d'un fournisseur doit être unique
        });

        // Table pour les lignes des factures fournisseurs
        Schema::create('supplier_invoice_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('supplier_invoice_id')->constrained('supplier_invoices')->cascadeOnDelete();
            $table->foreignUuid('product_id')->nullable()->constrained('products')->nullOnDelete()->comment('Produit acheté si applicable');
            // $table->foreignUuid('purchase_order_item_id')->nullable()->constrained('purchase_order_items')->nullOnDelete(); // Lier à la ligne de commande d'achat

            $table->string('description');
            $table->decimal('quantity', 15, 2);
            $table->decimal('unit_price', 15, 2)->comment('Prix unitaire HT facturé par le fournisseur');
            $table->decimal('discount_percentage', 5, 2)->default(0)->comment('Remise en % sur la ligne');
            $table->decimal('tax_rate', 5, 2)->default(0)->comment('Taux de TVA en %');
            // Colonnes calculées (non storedAs ici pour plus de flexibilité, sera dans le modèle)
            // $table->decimal('line_subtotal', 15, 2); // (qty * price) - discount
            // $table->decimal('line_tax_amount', 15, 2);
            $table->decimal('line_total', 15, 2)->comment('Total TTC pour la ligne');
            $table->timestamps();
        });

        // Table pour les paiements effectués aux fournisseurs
        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('payment_reference')->unique()->comment('Référence unique du paiement');
            $table->foreignUuid('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->comment('Utilisateur ayant enregistré le paiement');
            // $table->foreignUuid('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete(); // Si vous avez une table payment_methods
            $table->string('payment_method_name')->nullable()->comment('Ex: Virement, Chèque, Espèces');


            $table->date('payment_date');
            $table->decimal('amount', 15, 2);
            $table->text('notes')->nullable();
            $table->string('transaction_id')->nullable()->comment('ID de transaction bancaire si applicable');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // Table pivot pour lier les paiements aux factures fournisseurs (un paiement peut couvrir plusieurs factures)
        // Et une facture peut être payée par plusieurs paiements partiels.
        Schema::create('supplier_invoice_payment', function (Blueprint $table) {
            $table->id(); // Simple ID auto-incrémenté pour la table pivot
            $table->foreignUuid('supplier_payment_id')->constrained('supplier_payments')->cascadeOnDelete();
            $table->foreignUuid('supplier_invoice_id')->constrained('supplier_invoices')->cascadeOnDelete();
            $table->decimal('amount_applied', 15, 2)->comment('Montant de ce paiement appliqué à cette facture');
            $table->timestamps();

            $table->unique(['supplier_payment_id', 'supplier_invoice_id'], 'payment_invoice_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_invoice_payment');
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('supplier_invoice_items');
        Schema::dropIfExists('supplier_invoices');
    }
};
