<?php

declare(strict_types=1);

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

// Routes directes pour la génération de PDF des documents
// Ces routes seront accessibles sans authentification et sans middleware tenant

// Factures et avoirs clients
Route::get('/direct-pdf/invoice/{invoiceId}', [PdfGenerationController::class, 'downloadInvoice'])
     ->name('direct.invoice.pdf');
Route::get('/direct-pdf/credit-note/{creditNoteId}', [PdfGenerationController::class, 'downloadCreditNote'])
     ->name('direct.credit-note.pdf');
     
// Devis et commandes clients
Route::get('/direct-pdf/quotation/{quotationId}', [PdfGenerationController::class, 'downloadQuotation'])
     ->name('direct.quotation.pdf');
Route::get('/direct-pdf/sales-order/{salesOrderId}', [PdfGenerationController::class, 'downloadSalesOrder'])
     ->name('direct.sales-order.pdf');
     
// Bons de livraison
Route::get('/direct-pdf/delivery-note/{deliveryNoteId}', [PdfGenerationController::class, 'downloadDeliveryNote'])
     ->name('direct.delivery-note.pdf');
     
// Documents fournisseurs
Route::get('/direct-pdf/purchase-order/{purchaseOrderId}', [PdfGenerationController::class, 'downloadPurchaseOrder'])
     ->name('direct.purchase-order.pdf');
Route::get('/direct-pdf/supplier-credit-note/{supplierCreditNoteId}', [PdfGenerationController::class, 'downloadSupplierCreditNote'])
     ->name('direct.supplier-credit-note.pdf');
Route::get('/direct-pdf/supplier-invoice/{supplierInvoiceId}', [PdfGenerationController::class, 'downloadSupplierInvoice'])
     ->name('direct.supplier-invoice.pdf');

// Route de test simple pour déboguer
Route::get('/direct-pdf-test', function() {
    Log::info('Direct PDF test route accessed');
    return "Direct PDF test route OK";
});
