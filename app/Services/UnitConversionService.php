<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\UnitOfMeasure;
use Illuminate\Support\Facades\Log;

class UnitConversionService
{
    /**
     * Convertit une quantité d'une unité à une autre
     * 
     * @param Product $product Le produit concerné (pour le contexte)
     * @param float $quantity La quantité à convertir
     * @param UnitOfMeasure $fromUnit L'unité source
     * @param UnitOfMeasure $toUnit L'unité cible
     * @return float La quantité convertie
     * @throws \Exception Si la conversion n'est pas possible
     */
    public function convert(Product $product, float $quantity, UnitOfMeasure $fromUnit, UnitOfMeasure $toUnit): float
    {
        // Si les unités sont identiques
        if ($fromUnit->id === $toUnit->id) {
            return $quantity;
        }

        // Étape 1: Convertir fromUnit vers son unité de base (si elle en a une)
        $quantityInFromUnitBase = $fromUnit->convertToBaseUnit($quantity);
        $currentBaseUnit = $fromUnit->baseUnit ?? $fromUnit; // L'unité de base de fromUnit, ou fromUnit si c'est déjà une base

        // Étape 2: Si l'unité de base de fromUnit est la même que toUnit
        if ($currentBaseUnit->id === $toUnit->id) {
            return $quantityInFromUnitBase;
        }

        // Étape 3: Si toUnit est une dérivée de currentBaseUnit
        if ($toUnit->base_unit_id === $currentBaseUnit->id) {
            return $toUnit->convertFromBaseUnit($quantityInFromUnitBase);
        }
        
        // Étape 4: Si les deux unités partagent la même unité de base ultime
        if ($fromUnit->base_unit_id && $toUnit->base_unit_id && $fromUnit->base_unit_id === $toUnit->base_unit_id) {
            // Les deux unités ont la même unité de base
            return $toUnit->convertFromBaseUnit($quantityInFromUnitBase);
        }
        
        // Étape 5: Tentative de trouver un chemin de conversion plus complexe
        // Pour l'instant, cette implémentation est simplifiée
        // Une implémentation plus avancée pourrait construire un graphe des unités
        // et trouver le chemin de conversion le plus court
        
        // Journalisation de la tentative de conversion échouée
        Log::warning("Tentative de conversion échouée", [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'from_unit_id' => $fromUnit->id,
            'from_unit_name' => $fromUnit->name,
            'to_unit_id' => $toUnit->id,
            'to_unit_name' => $toUnit->name,
            'quantity' => $quantity
        ]);

        // Pour un MVP, si les cas ci-dessus ne s'appliquent pas, lever une exception
        throw new \Exception("Conversion d'unité non supportée directement entre {$fromUnit->name} et {$toUnit->name} pour le produit {$product->name}. Vérifiez la configuration des unités de base.");
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
