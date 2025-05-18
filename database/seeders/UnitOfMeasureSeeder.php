<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\UnitOfMeasure;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UnitOfMeasureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Unités dénombrables
        $piece = UnitOfMeasure::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Pièce',
            'symbol' => 'pc',
            'type' => 'countable',
            'is_active' => true,
        ]);

        UnitOfMeasure::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Carton',
            'symbol' => 'ctn',
            'type' => 'countable',
            'base_unit_id' => $piece->id,
            'conversion_factor' => 12, // 1 carton = 12 pièces
            'is_active' => true,
        ]);

        UnitOfMeasure::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Douzaine',
            'symbol' => 'dz',
            'type' => 'countable',
            'base_unit_id' => $piece->id,
            'conversion_factor' => 12, // 1 douzaine = 12 pièces
            'is_active' => true,
        ]);

        // Unités de poids
        $gramme = UnitOfMeasure::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Gramme',
            'symbol' => 'g',
            'type' => 'weight',
            'is_active' => true,
        ]);

        UnitOfMeasure::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Kilogramme',
            'symbol' => 'kg',
            'type' => 'weight',
            'base_unit_id' => $gramme->id,
            'conversion_factor' => 1000, // 1 kg = 1000 g
            'is_active' => true,
        ]);

        // Unités de volume
        $litre = UnitOfMeasure::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Litre',
            'symbol' => 'L',
            'type' => 'volume',
            'is_active' => true,
        ]);

        UnitOfMeasure::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Millilitre',
            'symbol' => 'mL',
            'type' => 'volume',
            'base_unit_id' => $litre->id,
            'conversion_factor' => 0.001, // 1 mL = 0.001 L
            'is_active' => true,
        ]);

        // Unités de longueur
        $metre = UnitOfMeasure::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Mètre',
            'symbol' => 'm',
            'type' => 'length',
            'is_active' => true,
        ]);

        UnitOfMeasure::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Centimètre',
            'symbol' => 'cm',
            'type' => 'length',
            'base_unit_id' => $metre->id,
            'conversion_factor' => 0.01, // 1 cm = 0.01 m
            'is_active' => true,
        ]);
    }
}
