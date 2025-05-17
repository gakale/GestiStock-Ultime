<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\TenantUser;
use App\Models\InvoiceItem;
use App\Models\Product;

class ClientInvoiceSeeder extends Seeder
{
    /**
     * Génère des clients et des factures avec différents statuts pour tester
     * le calcul du solde dû et du chiffre d'affaires total.
     */
    public function run(): void
    {
        // Récupérer un utilisateur pour l'associer aux factures
        $user = TenantUser::first();
        if (!$user) {
            $this->command->error('Aucun utilisateur trouvé. Veuillez d’abord créer un utilisateur.');
            return;
        }
        
        // Récupérer quelques produits pour les lignes de facture
        $products = Product::take(10)->get();
        if ($products->isEmpty()) {
            $this->command->error('Aucun produit trouvé. Veuillez d’abord créer des produits.');
            return;
        }
        
        // Créer 10 clients
        $clients = Client::factory(10)->create();
        
        $this->command->info('10 clients créés avec succès.');
        
        // Pour chaque client, créer des factures avec différents statuts
        foreach ($clients as $index => $client) {
            // Nous allons créer des factures avec différents statuts pour certains clients
            // en fonction de leur index
            
            // Client 1-3 : Facture payée
            if ($index < 3) {
                $this->createInvoiceWithItems($client, $user, $products, 'paid');
            }
            
            // Client 4-5 : Facture partiellement payée
            if ($index >= 3 && $index < 5) {
                $this->createInvoiceWithItems($client, $user, $products, 'partiallyPaid');
            }
            
            // Client 6-7 : Facture émise mais non payée
            if ($index >= 5 && $index < 7) {
                $this->createInvoiceWithItems($client, $user, $products, 'issued');
            }
            
            // Client 8 : Facture annulée
            if ($index === 7) {
                $this->createInvoiceWithItems($client, $user, $products, 'cancelled');
            }
            
            // Client 9 : Facture en brouillon
            if ($index === 8) {
                $this->createInvoiceWithItems($client, $user, $products, 'draft');
            }
            
            // Client 10 : Plusieurs factures avec différents statuts
            if ($index === 9) {
                $this->createInvoiceWithItems($client, $user, $products, 'paid');
                $this->createInvoiceWithItems($client, $user, $products, 'partiallyPaid');
                $this->createInvoiceWithItems($client, $user, $products, 'issued');
                $this->createInvoiceWithItems($client, $user, $products, 'cancelled');
                $this->createInvoiceWithItems($client, $user, $products, 'draft');
            }
        }
        
        $this->command->info('Factures créées avec succès pour tous les clients.');
    }
    
    /**
     * Crée une facture avec des lignes pour un client donné
     */
    private function createInvoiceWithItems(Client $client, TenantUser $user, $products, string $status): Invoice
    {
        // Créer la facture avec le statut spécifié
        $invoice = Invoice::factory()->{$status}()->create([
            'client_id' => $client->id,
            'user_id' => $user->id,
        ]);
        
        // Ajouter 1 à 3 lignes de facture
        $itemCount = rand(1, 3);
        
        for ($i = 0; $i < $itemCount; $i++) {
            $product = $products->random();
            $quantity = rand(1, 5);
            
            // Calculer le montant total de la ligne avec la remise
            $discountPercentage = rand(0, 10);
            $unitPrice = $product->selling_price;
            $lineTotal = $quantity * $unitPrice * (1 - ($discountPercentage / 100));
            
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku ?? 'SKU-' . str_pad((string)rand(1, 999), 3, '0', STR_PAD_LEFT),
                'description' => $product->description ?? $product->name,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'tax_rate' => 20, // TVA 20%
                'discount_percentage' => $discountPercentage,
                'line_total' => $lineTotal,
            ]);
        }
        
        // Recalculer les totaux de la facture
        $invoice->calculateTotals();
        $invoice->save();
        
        return $invoice;
    }
}
