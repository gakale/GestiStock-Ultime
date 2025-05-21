<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Product;
use App\Models\ProductLocationStock;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LocationManagementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Ce seeder crée une structure complète d'entrepôts, d'emplacements et de stocks
     * pour démontrer le système de gestion des emplacements.
     */
    public function run(): void
    {
        // Vérification si les données existent déjà
        if (Warehouse::where('name', 'Entrepôt Principal')->exists()) {
            echo "Les données de gestion des emplacements existent déjà. Seeder ignoré.\n";
            return;
        }
        
        // Création des entrepôts
        $warehouse1 = Warehouse::create([
            'id' => Str::uuid(),
            'name' => 'Entrepôt Principal',
            'address' => '123 Rue de la Logistique, 75001 Paris',
            'is_active' => true,
        ]);
        
        $warehouse2 = Warehouse::create([
            'id' => Str::uuid(),
            'name' => 'Entrepôt Secondaire',
            'address' => '456 Avenue du Stock, 69001 Lyon',
            'is_active' => true,
        ]);
        
        // Création des emplacements pour l'entrepôt principal
        $receivingDock = Location::create([
            'id' => Str::uuid(),
            'warehouse_id' => $warehouse1->id,
            'name' => 'Quai de Réception',
            'location_type' => 'receiving_dock',
            'barcode' => 'LOC-REC-001',
            'description' => 'Zone de réception des marchandises',
            'is_active' => true,
            'is_pickable' => false,
            'is_storable' => true,
        ]);
        
        $shippingDock = Location::create([
            'id' => Str::uuid(),
            'warehouse_id' => $warehouse1->id,
            'name' => 'Quai d\'Expédition',
            'location_type' => 'shipping_dock',
            'barcode' => 'LOC-SHIP-001',
            'description' => 'Zone d\'expédition des commandes',
            'is_active' => true,
            'is_pickable' => true,
            'is_storable' => false,
        ]);
        
        $storageZone = Location::create([
            'id' => Str::uuid(),
            'warehouse_id' => $warehouse1->id,
            'name' => 'Zone de Stockage A',
            'location_type' => 'storage_zone',
            'barcode' => 'LOC-STOR-A',
            'description' => 'Zone principale de stockage',
            'is_active' => true,
            'is_pickable' => true,
            'is_storable' => true,
        ]);
        
        // Création des sous-emplacements pour la zone de stockage
        $aisle1 = Location::create([
            'id' => Str::uuid(),
            'warehouse_id' => $warehouse1->id,
            'parent_id' => $storageZone->id,
            'name' => 'Allée A1',
            'location_type' => 'aisle',
            'barcode' => 'LOC-A1',
            'is_active' => true,
            'is_pickable' => true,
            'is_storable' => true,
        ]);
        
        $aisle2 = Location::create([
            'id' => Str::uuid(),
            'warehouse_id' => $warehouse1->id,
            'parent_id' => $storageZone->id,
            'name' => 'Allée A2',
            'location_type' => 'aisle',
            'barcode' => 'LOC-A2',
            'is_active' => true,
            'is_pickable' => true,
            'is_storable' => true,
        ]);
        
        // Création des étagères pour l'allée A1
        $shelf1 = Location::create([
            'id' => Str::uuid(),
            'warehouse_id' => $warehouse1->id,
            'parent_id' => $aisle1->id,
            'name' => 'Étagère A1-01',
            'location_type' => 'shelf',
            'barcode' => 'LOC-A1-01',
            'is_active' => true,
            'is_pickable' => true,
            'is_storable' => true,
        ]);
        
        $shelf2 = Location::create([
            'id' => Str::uuid(),
            'warehouse_id' => $warehouse1->id,
            'parent_id' => $aisle1->id,
            'name' => 'Étagère A1-02',
            'location_type' => 'shelf',
            'barcode' => 'LOC-A1-02',
            'is_active' => true,
            'is_pickable' => true,
            'is_storable' => true,
        ]);
        
        // Création des bacs pour l'étagère A1-01
        $bin1 = Location::create([
            'id' => Str::uuid(),
            'warehouse_id' => $warehouse1->id,
            'parent_id' => $shelf1->id,
            'name' => 'Bac A1-01-01',
            'location_type' => 'bin',
            'barcode' => 'LOC-A1-01-01',
            'is_active' => true,
            'is_pickable' => true,
            'is_storable' => true,
        ]);
        
        $bin2 = Location::create([
            'id' => Str::uuid(),
            'warehouse_id' => $warehouse1->id,
            'parent_id' => $shelf1->id,
            'name' => 'Bac A1-01-02',
            'location_type' => 'bin',
            'barcode' => 'LOC-A1-01-02',
            'is_active' => true,
            'is_pickable' => true,
            'is_storable' => true,
        ]);
        
        // Création d'une zone de contrôle qualité
        $qualityControl = Location::create([
            'id' => Str::uuid(),
            'warehouse_id' => $warehouse1->id,
            'name' => 'Contrôle Qualité',
            'location_type' => 'quality_control',
            'barcode' => 'LOC-QC-001',
            'description' => 'Zone de contrôle qualité des produits reçus',
            'is_active' => true,
            'is_pickable' => false,
            'is_storable' => true,
        ]);
        
        // Création d'une zone de retours
        $returns = Location::create([
            'id' => Str::uuid(),
            'warehouse_id' => $warehouse1->id,
            'name' => 'Zone Retours',
            'location_type' => 'returns',
            'barcode' => 'LOC-RET-001',
            'description' => 'Zone de traitement des retours clients',
            'is_active' => true,
            'is_pickable' => false,
            'is_storable' => true,
        ]);
        
        // Création des emplacements pour l'entrepôt secondaire
        $receivingDock2 = Location::create([
            'id' => Str::uuid(),
            'warehouse_id' => $warehouse2->id,
            'name' => 'Quai de Réception',
            'location_type' => 'receiving_dock',
            'barcode' => 'LOC-REC-002',
            'description' => 'Zone de réception des marchandises',
            'is_active' => true,
            'is_pickable' => false,
            'is_storable' => true,
        ]);
        
        $storageZone2 = Location::create([
            'id' => Str::uuid(),
            'warehouse_id' => $warehouse2->id,
            'name' => 'Zone de Stockage B',
            'location_type' => 'storage_zone',
            'barcode' => 'LOC-STOR-B',
            'description' => 'Zone principale de stockage',
            'is_active' => true,
            'is_pickable' => true,
            'is_storable' => true,
        ]);
        
        // Récupération de quelques produits existants pour ajouter du stock
        $products = Product::take(5)->get();
        
        if ($products->count() > 0) {
            // Ajout de stock pour les produits dans différents emplacements
            foreach ($products as $index => $product) {
                // Stock dans le bac 1
                ProductLocationStock::create([
                    'id' => Str::uuid(),
                    'product_id' => $product->id,
                    'location_id' => $bin1->id,
                    'quantity' => 10 * ($index + 1),
                ]);
                
                // Stock dans le bac 2
                ProductLocationStock::create([
                    'id' => Str::uuid(),
                    'product_id' => $product->id,
                    'location_id' => $bin2->id,
                    'quantity' => 5 * ($index + 1),
                ]);
                
                // Stock dans la zone de contrôle qualité (pour certains produits)
                if ($index % 2 === 0) {
                    ProductLocationStock::create([
                        'id' => Str::uuid(),
                        'product_id' => $product->id,
                        'location_id' => $qualityControl->id,
                        'quantity' => 3,
                    ]);
                }
                
                // Stock dans l'entrepôt secondaire
                ProductLocationStock::create([
                    'id' => Str::uuid(),
                    'product_id' => $product->id,
                    'location_id' => $storageZone2->id,
                    'quantity' => 20 * ($index + 1),
                ]);
            }
        }
    }
}
