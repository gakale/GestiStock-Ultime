<?php

declare(strict_types=1);

use App\Http\Controllers\PdfGenerationController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant routes for your application.
| These routes are loaded by the TenantRouteServiceProvider.
|
| Feel free to customize them however you want. Good luck!
|
*/

// Ajout d'un log pour vérifier que le fichier tenant.php est bien chargé
Log::info('Tenant routes file is loaded');

// Groupe de routes tenant avec middleware web et tenancy
Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    // Log pour vérifier que le groupe de routes est exécuté
    Log::info('Tenant routes group is executed');
    
    Route::get('/', function () {
        Log::info('Tenant root route accessed for tenant: ' . tenant('id'));
        return 'This is your multi-tenant application. The id of the current tenant is ' . tenant('id');
    });
    
    // Route de test très simple pour vérifier le routage
    Route::get('/invoices/test-route', function () {
        Log::info('Test route /invoices/test-route accessed for tenant: ' . tenant('id'));
        return "Test route OK for tenant: " . tenant('id');
    });
    
    // Route pour la génération de PDF des factures - accessible sans authentification
    // Ajout d'une route simple pour tester sans paramètres
    Route::get('/invoices/pdf-test', function() {
        Log::info('PDF test route accessed for tenant: ' . tenant('id'));
        return "PDF test route OK for tenant: " . tenant('id');
    });
    
    // Route pour la génération de PDF des factures - accessible sans authentification
    Route::get('/invoices/{invoiceId}/pdf', [PdfGenerationController::class, 'downloadInvoice'])
         ->name('tenant.invoices.pdf'); // Nommer la route est une bonne pratique
         
    // Route pour la génération de PDF des avoirs clients - accessible sans authentification
    Route::get('/credit-notes/{creditNoteId}/pdf', [PdfGenerationController::class, 'downloadCreditNote'])
         ->name('credit-notes.print'); // Nom de route utilisé dans ViewCreditNote.php
});
