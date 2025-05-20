<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\UnitOfMeasure;
use Illuminate\Support\Facades\Log;

class UnitConversionService
{
    /**
     * Obtient le facteur de conversion entre deux unités
     * 
     * @param UnitOfMeasure $fromUnit L'unité source
     * @param UnitOfMeasure $toUnit L'unité cible
     * @param Product $product Le produit concerné (pour le contexte)
     * @return float Le facteur de conversion
     * @throws \Exception Si la conversion n'est pas possible
     */
    public function getConversionFactor(UnitOfMeasure $fromUnit, UnitOfMeasure $toUnit, Product $product): float
    {
        // Si les unités sont identiques
        if ($fromUnit->id === $toUnit->id) {
            return 1.0;
        }
        
        // Si fromUnit est une unité de base et toUnit en dérive
        if ($toUnit->base_unit_id === $fromUnit->id) {
            return 1.0 / $toUnit->conversion_factor;
        }
        
        // Si toUnit est une unité de base et fromUnit en dérive
        if ($fromUnit->base_unit_id === $toUnit->id) {
            return $fromUnit->conversion_factor;
        }
        
        // Si les deux unités ont la même unité de base
        if ($fromUnit->base_unit_id && $toUnit->base_unit_id && $fromUnit->base_unit_id === $toUnit->base_unit_id) {
            return $fromUnit->conversion_factor / $toUnit->conversion_factor;
        }
        
        // Sinon, la conversion n'est pas possible
        throw new \Exception("Impossible de convertir entre {$fromUnit->name} et {$toUnit->name} pour le produit {$product->name}");
    }
    
    /**
     * Convertit une quantité d'une unité à une autre
     * 
     * @param float $quantity La quantité à convertir
     * @param UnitOfMeasure $fromUnit L'unité source
     * @param UnitOfMeasure $toUnit L'unité cible
     * @param Product|null $product Le produit concerné (pour le contexte)
     * @return float La quantité convertie
     * @throws \InvalidArgumentException Si la conversion n'est pas possible
     */
    public function convert(float $quantity, UnitOfMeasure $fromUnit, UnitOfMeasure $toUnit, ?Product $product = null): float
    {
        // Si les unités sont identiques
        if ($fromUnit->id === $toUnit->id) {
            return $quantity;
        }

        // Vérifier si les unités ont des facteurs de conversion
        if (!$fromUnit->conversion_factor && $fromUnit->base_unit_id) {
            throw new \InvalidArgumentException("L'unité source {$fromUnit->name} n'a pas de facteur de conversion défini.");
        }
        
        if (!$toUnit->conversion_factor && $toUnit->base_unit_id) {
            throw new \InvalidArgumentException("L'unité cible {$toUnit->name} n'a pas de facteur de conversion défini.");
        }

        // Étape 1: Convertir fromUnit vers son unité de base (si elle en a une)
        $quantityInFromUnitBase = $fromUnit->base_unit_id ? $quantity * $fromUnit->conversion_factor : $quantity;
        $currentBaseUnit = $fromUnit->base_unit_id ? UnitOfMeasure::find($fromUnit->base_unit_id) : $fromUnit;

        // Étape 2: Si l'unité de base de fromUnit est la même que toUnit
        if ($currentBaseUnit->id === $toUnit->id) {
            return $quantityInFromUnitBase;
        }

        // Étape 3: Si toUnit est une dérivée de currentBaseUnit
        if ($toUnit->base_unit_id === $currentBaseUnit->id) {
            return $quantityInFromUnitBase / $toUnit->conversion_factor;
        }
        
        // Étape 4: Si les deux unités partagent la même unité de base ultime
        if ($fromUnit->base_unit_id && $toUnit->base_unit_id && $fromUnit->base_unit_id === $toUnit->base_unit_id) {
            // Les deux unités ont la même unité de base
            return ($quantity * $fromUnit->conversion_factor) / $toUnit->conversion_factor;
        }
        
        // Journalisation de la tentative de conversion échouée
        $productInfo = $product ? "pour le produit {$product->name} (ID: {$product->id})" : "sans contexte de produit";
        Log::warning("Tentative de conversion échouée {$productInfo}", [
            'from_unit_id' => $fromUnit->id,
            'from_unit_name' => $fromUnit->name,
            'to_unit_id' => $toUnit->id,
            'to_unit_name' => $toUnit->name,
            'quantity' => $quantity
        ]);

        // Pour un MVP, si les cas ci-dessus ne s'appliquent pas, lever une exception
        throw new \InvalidArgumentException("Conversion d'unité non supportée directement entre {$fromUnit->name} et {$toUnit->name}. Vérifiez la configuration des unités de base.");
    }
    
    /**
     * Vérifie si une conversion est possible entre deux unités
     * 
     * @param UnitOfMeasure $fromUnit L'unité source
     * @param UnitOfMeasure $toUnit L'unité cible
     * @return bool True si la conversion est possible, false sinon
     */
    public function canConvert(UnitOfMeasure $fromUnit, UnitOfMeasure $toUnit): bool
    {
        // Si les unités sont identiques
        if ($fromUnit->id === $toUnit->id) {
            return true;
        }
        
        // Si fromUnit est une unité de base
        if ($fromUnit->isBaseUnit()) {
            // Si toUnit a fromUnit comme unité de base
            return $toUnit->base_unit_id === $fromUnit->id;
        }
        
        // Si toUnit est une unité de base
        if ($toUnit->isBaseUnit()) {
            // Si fromUnit a toUnit comme unité de base
            return $fromUnit->base_unit_id === $toUnit->id;
        }
        
        // Si les deux unités ont la même unité de base
        if ($fromUnit->base_unit_id && $toUnit->base_unit_id) {
            return $fromUnit->base_unit_id === $toUnit->base_unit_id;
        }
        
        // Sinon, la conversion n'est pas possible avec l'implémentation actuelle
        return false;
    }
}
