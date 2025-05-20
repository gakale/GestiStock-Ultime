<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ProductObserver
{
    /**
     * Handle the Product "created" event.
     */
    public function created(Product $product): void
    {
        // Si le produit est créé avec une quantité de stock initiale, créer un mouvement de stock
        if ($product->stock_quantity > 0) {
            try {
                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'initial',
                    'quantity_changed' => $product->stock_quantity,
                    'new_stock_quantity_after_movement' => $product->stock_quantity,
                    'movement_date' => now(),
                    'related_document_type' => 'App\\Models\\Product',
                    'related_document_id' => $product->id,
                    'user_id' => Auth::id(),
                    'reason' => 'Stock initial',
                    'notes' => 'Création du produit avec stock initial',
                    'transaction_unit_id' => $product->stock_unit_id,
                ]);

                Log::info("Mouvement de stock initial créé pour le produit {$product->name} (ID: {$product->id}) avec une quantité de {$product->stock_quantity}");
            } catch (\Exception $e) {
                Log::error("Erreur lors de la création du mouvement de stock initial pour le produit {$product->name} (ID: {$product->id}): {$e->getMessage()}");
            }
        }
    }

    /**
     * Handle the Product "updated" event.
     */
    public function updated(Product $product): void
    {
        // Si la quantité de stock a été modifiée directement (pas via un autre document)
        if ($product->isDirty('stock_quantity')) {
            $oldQuantity = $product->getOriginal('stock_quantity') ?? 0;
            $newQuantity = $product->stock_quantity;
            $change = $newQuantity - $oldQuantity;

            // Ne créer un mouvement que si la quantité a réellement changé
            if ($change != 0) {
                try {
                    StockMovement::create([
                        'product_id' => $product->id,
                        'type' => $change > 0 ? 'adjustment_in' : 'adjustment_out',
                        'quantity_changed' => $change,
                        'new_stock_quantity_after_movement' => $newQuantity,
                        'movement_date' => now(),
                        'related_document_type' => 'App\\Models\\Product',
                        'related_document_id' => $product->id,
                        'user_id' => Auth::id(),
                        'reason' => 'Ajustement manuel',
                        'notes' => "Ajustement manuel du stock de {$oldQuantity} à {$newQuantity}",
                        'transaction_unit_id' => $product->stock_unit_id,
                    ]);

                    Log::info("Mouvement d'ajustement de stock créé pour le produit {$product->name} (ID: {$product->id}) avec un changement de {$change}");
                } catch (\Exception $e) {
                    Log::error("Erreur lors de la création du mouvement d'ajustement de stock pour le produit {$product->name} (ID: {$product->id}): {$e->getMessage()}");
                }
            }
        }
    }
}
