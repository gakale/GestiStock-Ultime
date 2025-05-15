<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

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

Route::middleware([
    'web', // Middleware web standard (sessions, cookies, CSRF, etc.)
    InitializeTenancyByDomain::class, // Identifie le tenant basé sur le domaine
    PreventAccessFromCentralDomains::class, // Empêche l'accès à ces routes depuis les domaines centraux
])->group(function () {
    Route::get('/', function () {
        // dd(tenant()); // Pour vérifier que le tenant est bien identifié
        return 'This is your multi-tenant application. The id of the current tenant is ' . tenant('id');
    });
});
