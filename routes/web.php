<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PdfGenerationController;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// Route directe pour la génération de PDF des factures
// Cette route sera accessible sans authentification et sans middleware tenant
Route::get('/direct-pdf/{invoiceId}', [PdfGenerationController::class, 'downloadInvoice'])
     ->name('direct.invoices.pdf');

// Route de test simple pour déboguer
Route::get('/direct-pdf-test', function() {
    Log::info('Direct PDF test route accessed');
    return "Direct PDF test route OK";
});
