<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Initialiser l'application Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Product;
use App\Models\UnitOfMeasure;
use App\Services\UnitConversionService;
use Illuminate\Support\Facades\Log;

// Fonction d'aide pour les tests
function assert_equals($expected, $actual, $message = '') {
    if ($expected == $actual) {
        echo "✅ " . ($message ?: "Assertion réussie: $expected == $actual") . "\n";
    } else {
        echo "❌ " . ($message ?: "Assertion échouée: attendu $expected, obtenu $actual") . "\n";
    }
}

function assert_equals_delta($expected, $actual, $delta, $message = '') {
    if (abs($expected - $actual) <= $delta) {
        echo "✅ " . ($message ?: "Assertion réussie: $expected ≈ $actual (delta: $delta)") . "\n";
    } else {
        echo "❌ " . ($message ?: "Assertion échouée: attendu $expected, obtenu $actual (delta: $delta)") . "\n";
    }
}

echo "🔍 Démarrage des tests de conversion d'unités\n";
echo "=============================================\n\n";

// Créer le service de conversion
$conversionService = new UnitConversionService();

// Créer ou récupérer les unités de mesure
echo "📋 Création des unités de mesure...\n";

// Vérifier si les unités existent déjà
$pieceUnit = UnitOfMeasure::where('name', 'Pièce')->first();
if (!$pieceUnit) {
    $pieceUnit = UnitOfMeasure::create([
        'name' => 'Pièce',
        'symbol' => 'pc',
        'is_active' => true,
        'is_default' => true,
    ]);
}

$boxOf6Unit = UnitOfMeasure::where('name', 'Boîte de 6')->first();
if (!$boxOf6Unit) {
    $boxOf6Unit = UnitOfMeasure::create([
        'name' => 'Boîte de 6',
        'symbol' => 'B6',
        'is_active' => true,
        'base_unit_id' => $pieceUnit->id,
        'conversion_factor' => 6.0, // 1 boîte = 6 pièces
    ]);
}

$cartonOf12Unit = UnitOfMeasure::where('name', 'Carton de 12')->first();
if (!$cartonOf12Unit) {
    $cartonOf12Unit = UnitOfMeasure::create([
        'name' => 'Carton de 12',
        'symbol' => 'C12',
        'is_active' => true,
        'base_unit_id' => $pieceUnit->id,
        'conversion_factor' => 12.0, // 1 carton = 12 pièces
    ]);
}

echo "✅ Unités de mesure créées avec succès\n";
echo "  - Pièce (ID: {$pieceUnit->id})\n";
echo "  - Boîte de 6 (ID: {$boxOf6Unit->id})\n";
echo "  - Carton de 12 (ID: {$cartonOf12Unit->id})\n\n";

// Créer ou récupérer les produits de test
echo "📋 Création des produits de test...\n";

// Produit A
$productA = Product::where('sku', 'PROD-A')->first();
if (!$productA) {
    $productA = Product::create([
        'name' => 'Produit A',
        'slug' => 'produit-a',
        'sku' => 'PROD-A',
        'purchase_price' => 5.0,
        'base_purchase_price_unit_id' => $pieceUnit->id,
        'selling_price' => 10.0,
        'base_selling_price_unit_id' => $pieceUnit->id,
        'stock_unit_id' => $pieceUnit->id,
        'purchase_unit_id' => $boxOf6Unit->id,
        'sales_unit_id' => $pieceUnit->id,
        'is_active' => true,
    ]);
} else {
    $productA->update([
        'purchase_price' => 5.0,
        'base_purchase_price_unit_id' => $pieceUnit->id,
        'selling_price' => 10.0,
        'base_selling_price_unit_id' => $pieceUnit->id,
        'stock_unit_id' => $pieceUnit->id,
        'purchase_unit_id' => $boxOf6Unit->id,
        'sales_unit_id' => $pieceUnit->id,
    ]);
}

// Produit B
$productB = Product::where('sku', 'PROD-B')->first();
if (!$productB) {
    $productB = Product::create([
        'name' => 'Produit B',
        'slug' => 'produit-b',
        'sku' => 'PROD-B',
        'purchase_price' => 15.0,
        'base_purchase_price_unit_id' => $pieceUnit->id,
        'selling_price' => 20.0,
        'base_selling_price_unit_id' => $pieceUnit->id,
        'stock_unit_id' => $pieceUnit->id,
        'purchase_unit_id' => $pieceUnit->id,
        'sales_unit_id' => $pieceUnit->id,
        'is_active' => true,
    ]);
} else {
    $productB->update([
        'purchase_price' => 15.0,
        'base_purchase_price_unit_id' => $pieceUnit->id,
        'selling_price' => 20.0,
        'base_selling_price_unit_id' => $pieceUnit->id,
        'stock_unit_id' => $pieceUnit->id,
        'purchase_unit_id' => $pieceUnit->id,
        'sales_unit_id' => $pieceUnit->id,
    ]);
}

// Produit C
$productC = Product::where('sku', 'PROD-C')->first();
if (!$productC) {
    $productC = Product::create([
        'name' => 'Produit C',
        'slug' => 'produit-c',
        'sku' => 'PROD-C',
        'purchase_price' => 150.0,
        'base_purchase_price_unit_id' => $cartonOf12Unit->id,
        'selling_price' => 200.0,
        'base_selling_price_unit_id' => $cartonOf12Unit->id,
        'stock_unit_id' => $pieceUnit->id,
        'purchase_unit_id' => $cartonOf12Unit->id,
        'sales_unit_id' => $cartonOf12Unit->id,
        'is_active' => true,
    ]);
} else {
    $productC->update([
        'purchase_price' => 150.0,
        'base_purchase_price_unit_id' => $cartonOf12Unit->id,
        'selling_price' => 200.0,
        'base_selling_price_unit_id' => $cartonOf12Unit->id,
        'stock_unit_id' => $pieceUnit->id,
        'purchase_unit_id' => $cartonOf12Unit->id,
        'sales_unit_id' => $cartonOf12Unit->id,
    ]);
}

echo "✅ Produits créés avec succès\n";
echo "  - Produit A (ID: {$productA->id}): Prix achat 5€/Pièce, Unité achat par défaut: Boîte de 6\n";
echo "  - Produit B (ID: {$productB->id}): Prix vente 20€/Pièce, Unité vente par défaut: Pièce\n";
echo "  - Produit C (ID: {$productC->id}): Prix vente 200€/Carton de 12, Unité vente par défaut: Carton de 12\n\n";

// Exécuter les tests
echo "🧪 Exécution des tests de conversion d'unités...\n";
echo "------------------------------------------------\n\n";

echo "Test 1: Conversion de base entre unités\n";
echo "---------------------------------------\n";

// Test 1.1: Conversion de 1 Carton de 12 en Pièces
try {
    $result = $conversionService->convert(1, $cartonOf12Unit, $pieceUnit);
    assert_equals(12.0, $result, "1 Carton de 12 devrait être égal à 12 Pièces");
} catch (\Exception $e) {
    echo "❌ Erreur lors de la conversion de 1 Carton de 12 en Pièces: " . $e->getMessage() . "\n";
}

// Test 1.2: Conversion de 1 Pièce en Carton de 12
try {
    $result = $conversionService->convert(1, $pieceUnit, $cartonOf12Unit);
    assert_equals_delta(1/12, $result, 0.001, "1 Pièce devrait être égal à 1/12 Carton");
} catch (\Exception $e) {
    echo "❌ Erreur lors de la conversion de 1 Pièce en Carton de 12: " . $e->getMessage() . "\n";
}

// Test 1.3: Conversion de 1 Boîte de 6 en Pièces
try {
    $result = $conversionService->convert(1, $boxOf6Unit, $pieceUnit);
    assert_equals(6.0, $result, "1 Boîte de 6 devrait être égal à 6 Pièces");
} catch (\Exception $e) {
    echo "❌ Erreur lors de la conversion de 1 Boîte de 6 en Pièces: " . $e->getMessage() . "\n";
}

// Test 1.4: Conversion de 2 Boîtes de 6 en Carton de 12
try {
    $result = $conversionService->convert(2, $boxOf6Unit, $cartonOf12Unit);
    assert_equals(1.0, $result, "2 Boîtes de 6 devraient être égales à 1 Carton de 12");
} catch (\Exception $e) {
    echo "❌ Erreur lors de la conversion de 2 Boîtes de 6 en Carton de 12: " . $e->getMessage() . "\n";
}

echo "\nTest 2: Scénario Commande Fournisseur (Produit A)\n";
echo "------------------------------------------------\n";
echo "Produit A : Prix Achat = 5€, Unité Prix Achat = \"Pièce\". Unité Achat par défaut = \"Boîte de 6\".\n\n";

// Test 2.1: Calcul du prix d'une boîte de 6
try {
    $factor = $conversionService->convert(1, $boxOf6Unit, $pieceUnit);
    $boxPrice = $productA->purchase_price * $factor;
    
    assert_equals(6.0, $factor, "Le facteur de conversion devrait être 6");
    assert_equals(30.0, $boxPrice, "Le prix d'une boîte de 6 devrait être 30€");
} catch (\Exception $e) {
    echo "❌ Erreur lors du calcul du prix d'une boîte de 6: " . $e->getMessage() . "\n";
}

// Test 2.2: Calcul du prix d'une pièce
try {
    $factor = $conversionService->convert(1, $pieceUnit, $pieceUnit);
    $piecePrice = $productA->purchase_price * $factor;
    
    assert_equals(1.0, $factor, "Le facteur de conversion devrait être 1");
    assert_equals(5.0, $piecePrice, "Le prix d'une pièce devrait être 5€");
} catch (\Exception $e) {
    echo "❌ Erreur lors du calcul du prix d'une pièce: " . $e->getMessage() . "\n";
}

echo "\nTest 3: Scénario Facture Client (Produit B)\n";
echo "-------------------------------------------\n";
echo "Produit B : Prix Vente = 20€, Unité Prix Vente = \"Pièce\". Unité Vente par défaut = \"Pièce\".\n\n";

// Test 3.1: Calcul du prix d'une pièce
try {
    $factor = $conversionService->convert(1, $pieceUnit, $pieceUnit);
    $piecePrice = $productB->selling_price * $factor;
    
    assert_equals(1.0, $factor, "Le facteur de conversion devrait être 1");
    assert_equals(20.0, $piecePrice, "Le prix de vente d'une pièce devrait être 20€");
} catch (\Exception $e) {
    echo "❌ Erreur lors du calcul du prix d'une pièce: " . $e->getMessage() . "\n";
}

echo "\nTest 4: Scénario Facture Client (Produit C)\n";
echo "-------------------------------------------\n";
echo "Produit C : Prix Vente = 200€, Unité Prix Vente = \"Carton de 12\". Unité Vente par défaut = \"Carton de 12\".\n\n";

// Test 4.1: Calcul du prix d'un carton
try {
    $factor = $conversionService->convert(1, $cartonOf12Unit, $cartonOf12Unit);
    $cartonPrice = $productC->selling_price * $factor;
    
    assert_equals(1.0, $factor, "Le facteur de conversion devrait être 1");
    assert_equals(200.0, $cartonPrice, "Le prix de vente d'un carton devrait être 200€");
} catch (\Exception $e) {
    echo "❌ Erreur lors du calcul du prix d'un carton: " . $e->getMessage() . "\n";
}

// Test 4.2: Calcul du prix d'une pièce
try {
    $factor = $conversionService->convert(1, $pieceUnit, $cartonOf12Unit);
    $piecePrice = $productC->selling_price * $factor;
    
    assert_equals_delta(1/12, $factor, 0.001, "Le facteur de conversion devrait être 1/12");
    assert_equals_delta(16.67, $piecePrice, 0.01, "Le prix de vente d'une pièce devrait être environ 16.67€");
} catch (\Exception $e) {
    echo "❌ Erreur lors du calcul du prix d'une pièce: " . $e->getMessage() . "\n";
}

echo "\n=============================================\n";
echo "🏁 Fin des tests de conversion d'unités\n";
