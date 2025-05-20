<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Tenant;

$tenants = Tenant::all();

foreach ($tenants as $tenant) {
    echo "Tenant: " . $tenant->id . " (" . $tenant->name . ")\n";
    
    // Passer au contexte du tenant
    $tenant->makeCurrent();
    
    // Vérifier si la table existe
    if (Schema::hasTable('credit_note_items')) {
        $columns = Schema::getColumnListing('credit_note_items');
        echo "Colonnes de la table credit_note_items:\n";
        foreach ($columns as $column) {
            $type = DB::getSchemaBuilder()->getColumnType('credit_note_items', $column);
            echo "- $column ($type)\n";
        }
    } else {
        echo "La table credit_note_items n'existe pas pour ce tenant.\n";
    }
    
    // Revenir au contexte central
    $tenant->forget();
    
    echo "\n";
}
