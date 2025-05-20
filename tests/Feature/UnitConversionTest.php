<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\UnitOfMeasure;
use App\Services\UnitConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class UnitConversionTest extends TestCase
{
    use RefreshDatabase;

    protected UnitConversionService $conversionService;
    protected UnitOfMeasure $pieceUnit;
    protected UnitOfMeasure $boxOf6Unit;
    protected UnitOfMeasure $cartonOf12Unit;
    protected Product $productA;
    protected Product $productB;
    protected Product $productC;

    public function setUp(): void
    {
        parent::setUp();
        
        // Créer le service de conversion
        $this->conversionService = new UnitConversionService();
        
        // Créer les unités de mesure
        $this->pieceUnit = UnitOfMeasure::create([
            'name' => 'Pièce',
            'symbol' => 'pc',
            'is_active' => true,
            'is_default' => true,
        ]);
        
        $this->boxOf6Unit = UnitOfMeasure::create([
            'name' => 'Boîte de 6',
            'symbol' => 'B6',
            'is_active' => true,
            'base_unit_id' => $this->pieceUnit->id,
            'conversion_factor' => 6.0, // 1 boîte = 6 pièces
        ]);
        
        $this->cartonOf12Unit = UnitOfMeasure::create([
            'name' => 'Carton de 12',
            'symbol' => 'C12',
            'is_active' => true,
            'base_unit_id' => $this->pieceUnit->id,
            'conversion_factor' => 12.0, // 1 carton = 12 pièces
        ]);
        
        // Créer les produits de test
        $this->productA = Product::create([
            'name' => 'Produit A',
            'sku' => 'PROD-A',
            'purchase_price' => 5.0,
            'base_purchase_price_unit_id' => $this->pieceUnit->id,
            'selling_price' => 10.0,
            'base_selling_price_unit_id' => $this->pieceUnit->id,
            'stock_unit_id' => $this->pieceUnit->id,
            'purchase_unit_id' => $this->boxOf6Unit->id,
            'sales_unit_id' => $this->pieceUnit->id,
            'is_active' => true,
        ]);
        
        $this->productB = Product::create([
            'name' => 'Produit B',
            'sku' => 'PROD-B',
            'purchase_price' => 15.0,
            'base_purchase_price_unit_id' => $this->pieceUnit->id,
            'selling_price' => 20.0,
            'base_selling_price_unit_id' => $this->pieceUnit->id,
            'stock_unit_id' => $this->pieceUnit->id,
            'purchase_unit_id' => $this->pieceUnit->id,
            'sales_unit_id' => $this->pieceUnit->id,
            'is_active' => true,
        ]);
        
        $this->productC = Product::create([
            'name' => 'Produit C',
            'sku' => 'PROD-C',
            'purchase_price' => 150.0,
            'base_purchase_price_unit_id' => $this->cartonOf12Unit->id,
            'selling_price' => 200.0,
            'base_selling_price_unit_id' => $this->cartonOf12Unit->id,
            'stock_unit_id' => $this->pieceUnit->id,
            'purchase_unit_id' => $this->cartonOf12Unit->id,
            'sales_unit_id' => $this->cartonOf12Unit->id,
            'is_active' => true,
        ]);
    }

    /** @test */
    public function test_basic_unit_conversion()
    {
        // Test 1: Conversion de 1 Carton de 12 en Pièces
        $result = $this->conversionService->convert(1, $this->cartonOf12Unit, $this->pieceUnit);
        $this->assertEquals(12.0, $result, "1 Carton de 12 devrait être égal à 12 Pièces");
        
        // Test 2: Conversion de 1 Pièce en Carton de 12
        $result = $this->conversionService->convert(1, $this->pieceUnit, $this->cartonOf12Unit);
        $this->assertEquals(1/12, $result, "1 Pièce devrait être égal à 1/12 Carton");
        
        // Test 3: Conversion de 1 Boîte de 6 en Pièces
        $result = $this->conversionService->convert(1, $this->boxOf6Unit, $this->pieceUnit);
        $this->assertEquals(6.0, $result, "1 Boîte de 6 devrait être égal à 6 Pièces");
        
        // Test 4: Conversion de 2 Boîtes de 6 en Carton de 12
        $result = $this->conversionService->convert(2, $this->boxOf6Unit, $this->cartonOf12Unit);
        $this->assertEquals(1.0, $result, "2 Boîtes de 6 devraient être égales à 1 Carton de 12");
    }

    /** @test */
    public function test_product_a_purchase_price_conversion()
    {
        // Produit A : Prix Achat = 5€, Unité Prix Achat = "Pièce". Unité Achat par défaut = "Boîte de 6".
        
        // Calcul du prix d'une boîte de 6
        $factor = $this->conversionService->convert(1, $this->boxOf6Unit, $this->pieceUnit);
        $boxPrice = $this->productA->purchase_price * $factor;
        
        $this->assertEquals(6.0, $factor, "Le facteur de conversion devrait être 6");
        $this->assertEquals(30.0, $boxPrice, "Le prix d'une boîte de 6 devrait être 30€");
        
        // Calcul du prix d'une pièce
        $factor = $this->conversionService->convert(1, $this->pieceUnit, $this->pieceUnit);
        $piecePrice = $this->productA->purchase_price * $factor;
        
        $this->assertEquals(1.0, $factor, "Le facteur de conversion devrait être 1");
        $this->assertEquals(5.0, $piecePrice, "Le prix d'une pièce devrait être 5€");
    }

    /** @test */
    public function test_product_b_selling_price_conversion()
    {
        // Produit B : Prix Vente = 20€, Unité Prix Vente = "Pièce". Unité Vente par défaut = "Pièce".
        
        // Calcul du prix d'une pièce
        $factor = $this->conversionService->convert(1, $this->pieceUnit, $this->pieceUnit);
        $piecePrice = $this->productB->selling_price * $factor;
        
        $this->assertEquals(1.0, $factor, "Le facteur de conversion devrait être 1");
        $this->assertEquals(20.0, $piecePrice, "Le prix de vente d'une pièce devrait être 20€");
    }

    /** @test */
    public function test_product_c_selling_price_conversion()
    {
        // Produit C : Prix Vente = 200€, Unité Prix Vente = "Carton de 12". Unité Vente par défaut = "Carton de 12".
        
        // Calcul du prix d'un carton
        $factor = $this->conversionService->convert(1, $this->cartonOf12Unit, $this->cartonOf12Unit);
        $cartonPrice = $this->productC->selling_price * $factor;
        
        $this->assertEquals(1.0, $factor, "Le facteur de conversion devrait être 1");
        $this->assertEquals(200.0, $cartonPrice, "Le prix de vente d'un carton devrait être 200€");
        
        // Calcul du prix d'une pièce
        $factor = $this->conversionService->convert(1, $this->pieceUnit, $this->cartonOf12Unit);
        $piecePrice = $this->productC->selling_price * $factor;
        
        $this->assertEquals(1/12, $factor, "Le facteur de conversion devrait être 1/12");
        $this->assertEquals(200.0 * (1/12), $piecePrice, "Le prix de vente d'une pièce devrait être 16.67€");
        $this->assertEqualsWithDelta(16.67, $piecePrice, 0.01, "Le prix de vente d'une pièce devrait être environ 16.67€");
    }
}
