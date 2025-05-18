<?php

namespace Database\Seeders\Tenant;

use Illuminate\Database\Seeder;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\TenantUser;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoiceItem;
use App\Models\SupplierPayment;
use Carbon\Carbon;

class SupplierInvoicePaymentSeederFixed extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Générer un timestamp unique pour cette exécution du seeder avec un identifiant aléatoire
        $timestamp = date('YmdHis') . '-' . substr(md5(uniqid()), 0, 6);
        
        // Vérifier si nous avons déjà des fournisseurs, sinon en créer
        if (Supplier::count() == 0) {
            $this->command->info('Création des fournisseurs...');
            $suppliers = [
                [
                    'company_name' => 'Fournitures Express',
                ],
                [
                    'company_name' => 'Tech Solutions',
                ]
            ];

            foreach ($suppliers as $supplierData) {
                Supplier::create($supplierData);
            }
        }

        // Vérifier si nous avons déjà des produits, sinon en créer
        if (Product::count() == 0) {
            $this->command->info('Création des produits...');
            $products = [
                [
                    'name' => 'Ordinateur portable',
                    'sku' => 'ORD-001',
                    'purchase_price' => 800,
                    'selling_price' => 1200,
                ],
                [
                    'name' => 'Imprimante laser',
                    'sku' => 'IMP-001',
                    'purchase_price' => 300,
                    'selling_price' => 450,
                ],
                [
                    'name' => 'Écran 24 pouces',
                    'sku' => 'ECR-001',
                    'purchase_price' => 200,
                    'selling_price' => 300,
                ]
            ];

            foreach ($products as $productData) {
                Product::create($productData);
            }
        }

        // Récupérer les fournisseurs, produits et utilisateurs
        $suppliers = Supplier::all();
        $products = Product::all();
        $user = TenantUser::first();

        if (!$user) {
            $this->command->error('Aucun utilisateur trouvé. Veuillez créer un utilisateur avant d\'exécuter ce seeder.');
            return;
        }
        
        // Vérifier si nous avons au moins un fournisseur et un produit
        if ($suppliers->isEmpty() || $products->isEmpty()) {
            $this->command->error('Vous devez avoir au moins un fournisseur et un produit pour exécuter ce seeder.');
            return;
        }

        $this->command->info('Création des scénarios de factures et paiements...');

        // Scénario 1: Une facture entièrement payée en un seul paiement
        $this->createScenario1($suppliers->first(), $products->first(), $user, $timestamp);

        // Scénario 2: Une facture partiellement payée
        $this->createScenario2($suppliers->first(), $products->first(), $user, $timestamp);

        // Scénario 3: Un paiement pour plusieurs factures
        $this->createScenario3($suppliers->first(), $products->first(), $user, $timestamp);

        $this->command->info('Seeding terminé avec succès!');
    }

    /**
     * Scénario 1: Une facture entièrement payée en un seul paiement
     */
    private function createScenario1(Supplier $supplier, Product $product, $user, string $timestamp)
    {
        // Créer une facture
        $invoice = SupplierInvoice::create([
            'supplier_id' => $supplier->id,
            'user_id' => $user->id,
            'supplier_invoice_number' => 'FACT-' . $timestamp . '-001',
            'invoice_date' => Carbon::now()->subDays(15),
            'due_date' => Carbon::now()->addDays(15),
            'status' => 'pending',
            'subtotal' => 0,
            'taxes_amount' => 0,
            'shipping_charges' => 10,
            'total_amount' => 0,
            'amount_paid' => 0,
            'notes' => 'Scénario 1: Facture entièrement payée en un seul paiement',
        ]);

        // Ajouter des lignes à la facture
        SupplierInvoiceItem::create([
            'supplier_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'description' => $product->name,
            'quantity' => 1,
            'unit_price' => 800,
            'discount_percentage' => 0,
            'tax_rate' => 20,
            'line_total' => 960,
        ]);

        // Recalculer les totaux
        $invoice->calculateTotals();

        // Créer un paiement pour le montant exact
        $payment = SupplierPayment::create([
            'supplier_id' => $supplier->id,
            'user_id' => $user->id,
            'payment_reference' => 'PAY-' . $timestamp . '-001',
            'payment_date' => Carbon::now()->subDays(5),
            'payment_method_name' => 'bank_transfer',
            'amount' => $invoice->total_amount,
            'notes' => 'Paiement complet de la facture FACT-' . $timestamp . '-001',
        ]);

        // Attacher le paiement à la facture
        $payment->supplierInvoices()->attach($invoice->id, [
            'amount_applied' => $invoice->total_amount,
        ]);

        // Mettre à jour le montant payé et le statut
        $invoice->amount_paid = $invoice->total_amount;
        $invoice->status = 'paid';
        $invoice->saveQuietly();
    }

    /**
     * Scénario 2: Une facture partiellement payée
     */
    private function createScenario2(Supplier $supplier, Product $product, $user, string $timestamp)
    {
        // Créer une facture
        $invoice = SupplierInvoice::create([
            'supplier_id' => $supplier->id,
            'user_id' => $user->id,
            'supplier_invoice_number' => 'FACT-' . $timestamp . '-002',
            'invoice_date' => Carbon::now()->subDays(10),
            'due_date' => Carbon::now()->addDays(20),
            'status' => 'pending',
            'subtotal' => 0,
            'taxes_amount' => 0,
            'shipping_charges' => 15,
            'total_amount' => 0,
            'amount_paid' => 0,
            'notes' => 'Scénario 2: Facture partiellement payée',
        ]);

        // Ajouter des lignes à la facture
        SupplierInvoiceItem::create([
            'supplier_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'description' => $product->name,
            'quantity' => 2,
            'unit_price' => 300,
            'discount_percentage' => 5,
            'tax_rate' => 20,
            'line_total' => 684,
        ]);

        // Recalculer les totaux
        $invoice->calculateTotals();

        // Créer un paiement pour la moitié du montant
        $halfAmount = round($invoice->total_amount / 2, 2);
        $payment = SupplierPayment::create([
            'supplier_id' => $supplier->id,
            'user_id' => $user->id,
            'payment_reference' => 'PAY-' . $timestamp . '-002',
            'payment_date' => Carbon::now()->subDays(2),
            'payment_method_name' => 'cheque',
            'amount' => $halfAmount,
            'notes' => 'Premier acompte pour la facture FACT-' . $timestamp . '-002',
        ]);

        // Attacher le paiement à la facture
        $payment->supplierInvoices()->attach($invoice->id, [
            'amount_applied' => $halfAmount,
        ]);

        // Mettre à jour le montant payé et le statut
        $invoice->amount_paid = $halfAmount;
        $invoice->status = 'partially_paid';
        $invoice->saveQuietly();
    }

    /**
     * Scénario 3: Un paiement pour plusieurs factures
     */
    private function createScenario3(Supplier $supplier, Product $product, $user, string $timestamp)
    {
        // Créer la première facture
        $invoice1 = SupplierInvoice::create([
            'supplier_id' => $supplier->id,
            'user_id' => $user->id,
            'supplier_invoice_number' => 'FACT-' . $timestamp . '-003A',
            'invoice_date' => Carbon::now()->subDays(8),
            'due_date' => Carbon::now()->addDays(22),
            'status' => 'pending',
            'subtotal' => 0,
            'taxes_amount' => 0,
            'shipping_charges' => 5,
            'total_amount' => 0,
            'amount_paid' => 0,
            'notes' => 'Scénario 3: Première facture pour paiement groupé',
        ]);

        // Ajouter des lignes à la première facture
        SupplierInvoiceItem::create([
            'supplier_invoice_id' => $invoice1->id,
            'product_id' => $product->id,
            'description' => $product->name,
            'quantity' => 1,
            'unit_price' => 200,
            'discount_percentage' => 0,
            'tax_rate' => 20,
            'line_total' => 240,
        ]);

        // Recalculer les totaux
        $invoice1->calculateTotals();

        // Créer la deuxième facture
        $invoice2 = SupplierInvoice::create([
            'supplier_id' => $supplier->id,
            'user_id' => $user->id,
            'supplier_invoice_number' => 'FACT-' . $timestamp . '-003B',
            'invoice_date' => Carbon::now()->subDays(8),
            'due_date' => Carbon::now()->addDays(22),
            'status' => 'pending',
            'subtotal' => 0,
            'taxes_amount' => 0,
            'shipping_charges' => 8,
            'total_amount' => 0,
            'amount_paid' => 0,
            'notes' => 'Scénario 3: Deuxième facture pour paiement groupé',
        ]);

        // Ajouter des lignes à la deuxième facture
        SupplierInvoiceItem::create([
            'supplier_invoice_id' => $invoice2->id,
            'product_id' => $product->id,
            'description' => $product->name,
            'quantity' => 1,
            'unit_price' => 800,
            'discount_percentage' => 10,
            'tax_rate' => 20,
            'line_total' => 864,
        ]);

        // Recalculer les totaux
        $invoice2->calculateTotals();

        // Créer un paiement pour les deux factures
        $totalAmount = $invoice1->total_amount + $invoice2->total_amount;
        $payment = SupplierPayment::create([
            'supplier_id' => $supplier->id,
            'user_id' => $user->id,
            'payment_reference' => 'PAY-' . $timestamp . '-003',
            'payment_date' => Carbon::now(),
            'payment_method_name' => 'bank_transfer',
            'amount' => $totalAmount,
            'notes' => 'Paiement groupé pour les factures FACT-' . $timestamp . '-003A et FACT-' . $timestamp . '-003B',
        ]);

        // Attacher le paiement aux factures
        $payment->supplierInvoices()->attach($invoice1->id, [
            'amount_applied' => $invoice1->total_amount,
        ]);
        $payment->supplierInvoices()->attach($invoice2->id, [
            'amount_applied' => $invoice2->total_amount,
        ]);

        // Mettre à jour les montants payés et les statuts
        $invoice1->amount_paid = $invoice1->total_amount;
        $invoice1->status = 'paid';
        $invoice1->saveQuietly();

        $invoice2->amount_paid = $invoice2->total_amount;
        $invoice2->status = 'paid';
        $invoice2->saveQuietly();
    }
}
