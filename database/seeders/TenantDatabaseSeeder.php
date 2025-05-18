<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\Tenant\SupplierInvoicePaymentSeeder;

class TenantDatabaseSeeder extends Seeder
{
    /**
     * Seed the tenant's database.
     */
    public function run(): void
    {
        $this->call([
            // Ajoutez ici vos autres seeders tenant
            SupplierInvoicePaymentSeeder::class,
        ]);
    }
}
