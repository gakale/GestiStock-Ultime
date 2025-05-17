<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $invoiceDate = $this->faker->dateTimeBetween('-6 months', 'now');
        $dueDate = (clone $invoiceDate)->modify('+30 days');
        $status = $this->faker->randomElement(['draft', 'issued', 'partially_paid', 'paid', 'cancelled']);
        
        $subtotal = $this->faker->randomFloat(2, 100, 5000);
        $taxesAmount = $subtotal * 0.20; // TVA 20%
        $discountAmount = $this->faker->randomFloat(2, 0, $subtotal * 0.1); // Remise max 10%
        $shippingCharges = $this->faker->randomFloat(2, 0, 50);
        $totalAmount = $subtotal + $taxesAmount - $discountAmount + $shippingCharges;
        
        // Déterminer le montant payé en fonction du statut
        $amountPaid = match($status) {
            'paid' => $totalAmount,
            'partially_paid' => $this->faker->randomFloat(2, 1, $totalAmount - 0.01),
            'issued', 'draft', 'cancelled' => 0,
            default => 0,
        };
        
        return [
            'invoice_number' => null, // Sera généré automatiquement par le modèle
            'client_id' => null, // À définir lors de la création
            'user_id' => null, // Sera généré automatiquement ou défini lors de la création
            'invoice_date' => $invoiceDate,
            'due_date' => $dueDate,
            'status' => $status,
            'subtotal' => $subtotal,
            'taxes_amount' => $taxesAmount,
            'discount_amount' => $discountAmount,
            'shipping_charges' => $shippingCharges,
            'total_amount' => $totalAmount,
            'amount_paid' => $amountPaid,
            'payment_terms' => $this->faker->randomElement(['30 jours', '60 jours', 'Paiement à réception']),
            'notes_to_client' => $this->faker->boolean(30) ? $this->faker->paragraph() : null,
            'internal_notes' => $this->faker->boolean(20) ? $this->faker->paragraph() : null,
            'source_document_type' => null,
            'source_document_id' => null,
        ];
    }
    
    /**
     * Facture en brouillon
     */
    public function draft(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'draft',
                'amount_paid' => 0,
            ];
        });
    }
    
    /**
     * Facture émise mais non payée
     */
    public function issued(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'issued',
                'amount_paid' => 0,
            ];
        });
    }
    
    /**
     * Facture partiellement payée
     */
    public function partiallyPaid(): self
    {
        return $this->state(function (array $attributes) {
            $totalAmount = $attributes['total_amount'] ?? 1000;
            return [
                'status' => 'partially_paid',
                'amount_paid' => $this->faker->randomFloat(2, 1, $totalAmount - 0.01),
            ];
        });
    }
    
    /**
     * Facture totalement payée
     */
    public function paid(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'paid',
                'amount_paid' => $attributes['total_amount'] ?? 1000,
            ];
        });
    }
    
    /**
     * Facture annulée
     */
    public function cancelled(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'cancelled',
                'amount_paid' => 0,
            ];
        });
    }
}
