<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\CreditNote;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\DeliveryNote;
use App\Models\PurchaseOrder;
use App\Models\SupplierCreditNote;
use App\Models\SupplierInvoice;
use Illuminate\Http\Request;
use Spatie\LaravelPdf\Facades\Pdf; // Utiliser la Facade
use Illuminate\Support\Facades\App; // Pour le contexte du tenant
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

class PdfGenerationController extends Controller
{
    /**
     * Récupère les détails de l'entreprise à partir des données du tenant
     * 
     * @return array
     */
    private function getCompanyDetails(): array
    {
        $tenantData = tenant()->data ?? [];
        
        // Traitement spécial pour le logo
        $logoPath = null;
        if (isset($tenantData['company_logo_path'])) {
            // Si c'est un tableau (format Filament FileUpload), prendre le premier élément
            if (is_array($tenantData['company_logo_path'])) {
                $logoPath = $tenantData['company_logo_path'][0] ?? null;
            } else {
                // Sinon, utiliser directement la valeur
                $logoPath = $tenantData['company_logo_path'];
            }
            
            // Log pour débogage
            Log::info("[PdfGenerationController] Chemin du logo: {$logoPath}");
        }
        
        return [
            'name' => $tenantData['company_legal_name'] ?? tenant()->name ?? 'Votre Entreprise',
            'address_line1' => $tenantData['company_address_line1'] ?? 'Votre Adresse Ligne 1',
            'address_line2' => $tenantData['company_address_line2'] ?? null,
            'postal_code' => $tenantData['company_postal_code'] ?? 'Votre CP',
            'city' => $tenantData['company_city'] ?? 'Votre Ville',
            'country' => $tenantData['company_country'] ?? 'Votre Pays',
            'phone' => $tenantData['company_phone'] ?? null,
            'email' => $tenantData['company_email'] ?? null,
            'website' => $tenantData['company_website'] ?? null,
            'vat_number' => $tenantData['company_vat_number'] ?? null,
            'bank_details' => $tenantData['company_bank_details'] ?? null,
            'payment_terms' => $tenantData['invoice_payment_terms'] ?? null,
            'footer_notes' => $tenantData['invoice_footer_notes'] ?? null,
            'logo_path' => $logoPath,
        ];
    }
    
    /**
     * Initialise le contexte du tenant si nécessaire
     *
     * @return void
     */
    private function initializeTenantContext()
    {
        // Vérifier si nous sommes dans un contexte de tenant
        if (!tenant()) {
            // Si nous ne sommes pas dans un contexte tenant, essayer de déterminer le tenant à partir de l'hôte
            Log::info("[PdfGenerationController] Tentative de détermination du tenant à partir de l'hôte: " . request()->getHost());
            
            // Récupérer le domaine à partir de la requête
            $domain = request()->getHost();
            
            // Supprimer le port s'il est présent
            if (strpos($domain, ':') !== false) {
                $domain = explode(':', $domain)[0];
            }
            
            Log::info("[PdfGenerationController] Domaine extrait: {$domain}");
            
            // Essayer de trouver un tenant avec ce domaine
            $tenantDomain = \Stancl\Tenancy\Database\Models\Domain::where('domain', $domain)->first();
            
            if ($tenantDomain) {
                $tenant = $tenantDomain->tenant;
                Log::info("[PdfGenerationController] Tenant trouvé via le domaine: " . $tenant->id);
                
                // Initialiser le tenant manuellement
                tenancy()->initialize($tenant);
                
                Log::info("[PdfGenerationController] Tenant initialisé: " . tenant('id'));
            } else {
                Log::error("[PdfGenerationController] Aucun tenant trouvé pour le domaine: {$domain}");
                abort(404, "Tenant not found for domain: {$domain}");
            }
        }
    }

    public function downloadInvoice(string $invoiceId)
    {
        try {
            // Déboguer le contexte du tenant
            Log::info("[PdfGenerationController] Début de downloadInvoice pour ID: {$invoiceId}");
            Log::info("[PdfGenerationController] Tenant actuel: " . (tenant() ? tenant('id') : 'Aucun tenant'));
            
            // Initialiser le contexte du tenant si nécessaire
            $this->initializeTenantContext();
            
            try {
                // Charger la facture avec ses relations
                Log::info("[PdfGenerationController] Tentative de chargement de la facture ID: {$invoiceId}");
                
                // Vérifier d'abord si la facture existe
                $invoiceExists = Invoice::where('id', $invoiceId)->exists();
                Log::info("[PdfGenerationController] La facture existe: " . ($invoiceExists ? 'Oui' : 'Non'));
                
                if (!$invoiceExists) {
                    Log::error("[PdfGenerationController] Facture non trouvée avec ID: {$invoiceId}");
                    return Response::make('Facture non trouvée', 404);
                }
                
                // Charger la facture avec ses relations disponibles
                $invoice = Invoice::where('id', $invoiceId)->first();
                
                // Vérifier les relations disponibles
                $hasClientRelation = method_exists($invoice, 'client');
                $hasItemsRelation = method_exists($invoice, 'items');
                $hasUserRelation = method_exists($invoice, 'user');
                
                Log::info("[PdfGenerationController] Relations disponibles - client: {$hasClientRelation}, items: {$hasItemsRelation}, user: {$hasUserRelation}");
                
                // Charger les relations disponibles
                if ($hasClientRelation) {
                    $invoice->load('client');
                }
                
                if ($hasItemsRelation) {
                    $invoice->load('items');
                    // Vérifier si les items ont une relation product
                    if ($invoice->items->count() > 0 && method_exists($invoice->items->first(), 'product')) {
                        $invoice->load('items.product');
                    }
                }
                
                if ($hasUserRelation) {
                    $invoice->load('user');
                }
                
                Log::info("[PdfGenerationController] Facture chargée avec succès: {$invoice->invoice_number}");
                // Récupérer les informations de l'entreprise depuis les données du tenant
                $companyDetails = $this->getCompanyDetails();
                
                Log::info("[PdfGenerationController] Génération du PDF pour la facture: {$invoice->invoice_number}");
                
                // Générer le PDF avec la vue
                Log::info("[PdfGenerationController] Génération et téléchargement du PDF");
                return Pdf::view('pdf.invoice', [
                            'invoice' => $invoice,
                            'company' => $companyDetails,
                        ])
                        ->format('a4')
                        ->name('Facture-' . $invoice->invoice_number . '.pdf')
                        ->download(); // Forcer le téléchargement pour éviter les problèmes d'affichage
                
                // Pour déboguer, retourner la vue HTML au lieu du PDF
                // Log::info("[PdfGenerationController] Retour de la vue HTML pour débogage");
                // return view('pdf.invoice', ['invoice' => $invoice, 'company' => $companyDetails]);
            } catch (\Exception $e) {
                Log::error("[PdfGenerationController] Erreur lors du chargement de la facture: " . $e->getMessage());
                Log::error("[PdfGenerationController] Trace: " . $e->getTraceAsString());
                return Response::make('Erreur lors du chargement de la facture: ' . $e->getMessage(), 500);
            }
        } catch (\Exception $e) {
            Log::error("[PdfGenerationController] Erreur générale: " . $e->getMessage());
            Log::error("[PdfGenerationController] Trace: " . $e->getTraceAsString());
            return Response::make('Erreur générale: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Génère et télécharge un PDF d'avoir client
     *
     * @param string $creditNoteId L'ID de l'avoir client à télécharger
     * @return mixed
     */
    public function downloadCreditNote(string $creditNoteId)
    {
        try {
            // Déboguer le contexte du tenant
            Log::info("[PdfGenerationController] Début de downloadCreditNote pour ID: {$creditNoteId}");
            Log::info("[PdfGenerationController] Tenant actuel: " . (tenant() ? tenant('id') : 'Aucun tenant'));
            
            // Initialiser le contexte du tenant si nécessaire
            $this->initializeTenantContext();
            
            try {
                // Charger l'avoir avec ses relations
                Log::info("[PdfGenerationController] Tentative de chargement de l'avoir ID: {$creditNoteId}");
                
                // Vérifier d'abord si l'avoir existe
                $creditNoteExists = CreditNote::where('id', $creditNoteId)->exists();
                Log::info("[PdfGenerationController] L'avoir existe: " . ($creditNoteExists ? 'Oui' : 'Non'));
                
                if (!$creditNoteExists) {
                    Log::error("[PdfGenerationController] Avoir non trouvé avec ID: {$creditNoteId}");
                    return Response::make('Avoir non trouvé', 404);
                }
                
                // Charger l'avoir avec ses relations disponibles
                $creditNote = CreditNote::where('id', $creditNoteId)->first();
                
                // Charger les relations disponibles
                $creditNote->load(['client', 'items', 'items.product', 'invoice', 'user']);
                
                Log::info("[PdfGenerationController] Avoir chargé avec succès: {$creditNote->credit_note_number}");
                // Récupérer les informations de l'entreprise depuis les données du tenant
                $companyDetails = $this->getCompanyDetails();
                
                Log::info("[PdfGenerationController] Génération du PDF pour l'avoir: {$creditNote->credit_note_number}");
                
                // Générer le PDF avec la vue
                Log::info("[PdfGenerationController] Génération et téléchargement du PDF");
                return Pdf::view('pdf.credit-note', [
                            'creditNote' => $creditNote,
                            'company' => $companyDetails,
                        ])
                        ->format('a4')
                        ->name('Avoir-' . $creditNote->credit_note_number . '.pdf')
                        ->download(); // Forcer le téléchargement pour éviter les problèmes d'affichage
                
            } catch (\Exception $e) {
                Log::error("[PdfGenerationController] Erreur lors du chargement de l'avoir: " . $e->getMessage());
                Log::error("[PdfGenerationController] Trace: " . $e->getTraceAsString());
                return Response::make('Erreur lors du chargement de l\'avoir: ' . $e->getMessage(), 500);
            }
        } catch (\Exception $e) {
            Log::error("[PdfGenerationController] Erreur générale: " . $e->getMessage());
            Log::error("[PdfGenerationController] Trace: " . $e->getTraceAsString());
            return Response::make('Erreur générale: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Génère et télécharge un PDF de devis
     *
     * @param string $quotationId L'ID du devis à télécharger
     * @return mixed
     */
    public function downloadQuotation(string $quotationId)
    {
        try {
            Log::info("[PdfGenerationController] Début de downloadQuotation pour ID: {$quotationId}");
            // Initialiser le contexte du tenant si nécessaire
            $this->initializeTenantContext();

            try {
                // Vérifier d'abord si le devis existe
                $quotationExists = Quotation::where('id', $quotationId)->exists();
                Log::info("[PdfGenerationController] Le devis existe: " . ($quotationExists ? 'Oui' : 'Non'));
                
                if (!$quotationExists) {
                    Log::error("[PdfGenerationController] Devis non trouvé ID: {$quotationId}");
                    return Response::make('Devis non trouvé.', 404);
                }
                
                // Charger le devis avec ses relations
                $quotation = Quotation::with(['client', 'items', 'items.product', 'items.transactionUnit', 'user'])
                                ->findOrFail($quotationId);
                
                Log::info("[PdfGenerationController] Devis chargé avec succès: {$quotation->quotation_number}");
                // Récupérer les informations de l'entreprise depuis les données du tenant
                $companyDetails = $this->getCompanyDetails();

                Log::info("[PdfGenerationController] Génération du PDF pour le devis: {$quotation->quotation_number}");
                return Pdf::view('pdf.quotation', [
                            'quotation' => $quotation,
                            'company' => $companyDetails,
                        ])
                        ->format('a4')
                        ->name('Devis-' . $quotation->quotation_number . '.pdf')
                        ->download();
                        
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                Log::error("[PdfGenerationController] Devis non trouvé ID: {$quotationId} - " . $e->getMessage());
                return Response::make('Devis non trouvé.', 404);
            } catch (\Exception $e) {
                Log::error("[PdfGenerationController] Erreur downloadQuotation ID: {$quotationId} - " . $e->getMessage());
                Log::error("[PdfGenerationController] Trace: " . $e->getTraceAsString());
                return Response::make('Erreur lors de la génération du PDF du devis: ' . $e->getMessage(), 500);
            }
        } catch (\Exception $e) {
            Log::error("[PdfGenerationController] Erreur générale: " . $e->getMessage());
            Log::error("[PdfGenerationController] Trace: " . $e->getTraceAsString());
            return Response::make('Erreur générale: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Génère et télécharge un PDF de commande client
     *
     * @param string $salesOrderId L'ID de la commande client à télécharger
     * @return mixed
     */
    public function downloadSalesOrder(string $salesOrderId)
    {
        try {
            Log::info("[PdfGenerationController] Début de downloadSalesOrder pour ID: {$salesOrderId}");
            $this->initializeTenantContext();

            try {
                // Vérifier si la commande client existe
                $salesOrderExists = SalesOrder::where('id', $salesOrderId)->exists();
                
                if (!$salesOrderExists) {
                    Log::error("[PdfGenerationController] Commande client non trouvée ID: {$salesOrderId}");
                    return Response::make('Commande client non trouvée.', 404);
                }
                
                // Charger la commande client avec ses relations
                $salesOrder = SalesOrder::with(['client', 'items', 'items.product', 'items.transactionUnit', 'user'])
                                ->findOrFail($salesOrderId);
                // Récupérer les informations de l'entreprise depuis les données du tenant
                $companyDetails = $this->getCompanyDetails();

                // Vérifier si le template existe
                if (!view()->exists('pdf.sales-order')) {
                    Log::error("[PdfGenerationController] Template pdf.sales-order n'existe pas");
                    return Response::make('Template PDF non disponible. Veuillez contacter l\'administrateur.', 500);
                }

                return Pdf::view('pdf.sales-order', [
                            'salesOrder' => $salesOrder,
                            'company' => $companyDetails,
                        ])
                        ->format('a4')
                        ->name('Commande-' . $salesOrder->order_number . '.pdf')
                        ->download();
                        
            } catch (\Exception $e) {
                Log::error("[PdfGenerationController] Erreur downloadSalesOrder ID: {$salesOrderId} - " . $e->getMessage());
                Log::error("[PdfGenerationController] Trace: " . $e->getTraceAsString());
                return Response::make('Erreur lors de la génération du PDF de la commande client: ' . $e->getMessage(), 500);
            }
        } catch (\Exception $e) {
            Log::error("[PdfGenerationController] Erreur générale: " . $e->getMessage());
            return Response::make('Erreur générale: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Génère et télécharge un PDF de bon de livraison
     *
     * @param string $deliveryNoteId L'ID du bon de livraison à télécharger
     * @return mixed
     */
    public function downloadDeliveryNote(string $deliveryNoteId)
    {
        try {
            Log::info("[PdfGenerationController] Début de downloadDeliveryNote pour ID: {$deliveryNoteId}");
            $this->initializeTenantContext();

            try {
                // Vérifier si le bon de livraison existe
                $deliveryNoteExists = DeliveryNote::where('id', $deliveryNoteId)->exists();
                
                if (!$deliveryNoteExists) {
                    Log::error("[PdfGenerationController] Bon de livraison non trouvé ID: {$deliveryNoteId}");
                    return Response::make('Bon de livraison non trouvé.', 404);
                }
                
                // Charger le bon de livraison avec ses relations
                $deliveryNote = DeliveryNote::with(['client', 'items', 'items.product', 'items.transactionUnit', 'salesOrder', 'user'])
                                  ->findOrFail($deliveryNoteId);
                // Récupérer les informations de l'entreprise depuis les données du tenant
                $companyDetails = $this->getCompanyDetails();

                // Vérifier si le template existe
                if (!view()->exists('pdf.delivery-note')) {
                    Log::error("[PdfGenerationController] Template pdf.delivery-note n'existe pas");
                    return Response::make('Template PDF non disponible. Veuillez contacter l\'administrateur.', 500);
                }

                return Pdf::view('pdf.delivery-note', [
                            'deliveryNote' => $deliveryNote,
                            'company' => $companyDetails,
                        ])
                        ->format('a4')
                        ->name('BL-' . $deliveryNote->delivery_note_number . '.pdf')
                        ->download();
                        
            } catch (\Exception $e) {
                Log::error("[PdfGenerationController] Erreur downloadDeliveryNote ID: {$deliveryNoteId} - " . $e->getMessage());
                Log::error("[PdfGenerationController] Trace: " . $e->getTraceAsString());
                return Response::make('Erreur lors de la génération du PDF du bon de livraison: ' . $e->getMessage(), 500);
            }
        } catch (\Exception $e) {
            Log::error("[PdfGenerationController] Erreur générale: " . $e->getMessage());
            return Response::make('Erreur générale: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Génère et télécharge un PDF de commande fournisseur
     *
     * @param string $purchaseOrderId L'ID de la commande fournisseur à télécharger
     * @return mixed
     */
    public function downloadPurchaseOrder(string $purchaseOrderId)
    {
        try {
            Log::info("[PdfGenerationController] Début de downloadPurchaseOrder pour ID: {$purchaseOrderId}");
            $this->initializeTenantContext();

            try {
                // Vérifier si la commande fournisseur existe
                $purchaseOrderExists = PurchaseOrder::where('id', $purchaseOrderId)->exists();
                
                if (!$purchaseOrderExists) {
                    Log::error("[PdfGenerationController] Commande fournisseur non trouvée ID: {$purchaseOrderId}");
                    return Response::make('Commande fournisseur non trouvée.', 404);
                }
                
                // Charger la commande fournisseur avec ses relations
                $purchaseOrder = PurchaseOrder::with(['supplier', 'items', 'items.product', 'items.transactionUnit', 'user'])
                                   ->findOrFail($purchaseOrderId);
                // Récupérer les informations de l'entreprise depuis les données du tenant
                $companyDetails = $this->getCompanyDetails();

                // Vérifier si le template existe
                if (!view()->exists('pdf.purchase-order')) {
                    Log::error("[PdfGenerationController] Template pdf.purchase-order n'existe pas");
                    return Response::make('Template PDF non disponible. Veuillez contacter l\'administrateur.', 500);
                }

                return Pdf::view('pdf.purchase-order', [
                            'purchaseOrder' => $purchaseOrder,
                            'company' => $companyDetails,
                        ])
                        ->format('a4')
                        ->name('Commande-Fournisseur-' . $purchaseOrder->order_number . '.pdf')
                        ->download();
                        
            } catch (\Exception $e) {
                Log::error("[PdfGenerationController] Erreur downloadPurchaseOrder ID: {$purchaseOrderId} - " . $e->getMessage());
                Log::error("[PdfGenerationController] Trace: " . $e->getTraceAsString());
                return Response::make('Erreur lors de la génération du PDF de la commande fournisseur: ' . $e->getMessage(), 500);
            }
        } catch (\Exception $e) {
            Log::error("[PdfGenerationController] Erreur générale: " . $e->getMessage());
            return Response::make('Erreur générale: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Génère et télécharge un PDF d'avoir fournisseur
     *
     * @param string $supplierCreditNoteId L'ID de l'avoir fournisseur à télécharger
     * @return mixed
     */
    public function downloadSupplierCreditNote(string $supplierCreditNoteId)
    {
        try {
            Log::info("[PdfGenerationController] Début de downloadSupplierCreditNote pour ID: {$supplierCreditNoteId}");
            $this->initializeTenantContext();

            try {
                // Vérifier si l'avoir fournisseur existe
                $supplierCreditNoteExists = SupplierCreditNote::where('id', $supplierCreditNoteId)->exists();
                
                if (!$supplierCreditNoteExists) {
                    Log::error("[PdfGenerationController] Avoir fournisseur non trouvé ID: {$supplierCreditNoteId}");
                    return Response::make('Avoir fournisseur non trouvé.', 404);
                }
                
                // Charger l'avoir fournisseur avec ses relations
                $supplierCreditNote = SupplierCreditNote::with(['supplier', 'items', 'items.product', 'items.transactionUnit', 'user'])
                                         ->findOrFail($supplierCreditNoteId);
                // Récupérer les informations de l'entreprise depuis les données du tenant
                $companyDetails = $this->getCompanyDetails();

                // Vérifier si le template existe
                if (!view()->exists('pdf.supplier-credit-note')) {
                    Log::error("[PdfGenerationController] Template pdf.supplier-credit-note n'existe pas");
                    return Response::make('Template PDF non disponible. Veuillez contacter l\'administrateur.', 500);
                }

                return Pdf::view('pdf.supplier-credit-note', [
                            'supplierCreditNote' => $supplierCreditNote,
                            'company' => $companyDetails,
                        ])
                        ->format('a4')
                        ->name('Avoir-Fournisseur-' . $supplierCreditNote->credit_note_number . '.pdf')
                        ->download();
                        
            } catch (\Exception $e) {
                Log::error("[PdfGenerationController] Erreur downloadSupplierCreditNote ID: {$supplierCreditNoteId} - " . $e->getMessage());
                Log::error("[PdfGenerationController] Trace: " . $e->getTraceAsString());
                return Response::make('Erreur lors de la génération du PDF de l\'avoir fournisseur: ' . $e->getMessage(), 500);
            }
        } catch (\Exception $e) {
            Log::error("[PdfGenerationController] Erreur générale: " . $e->getMessage());
            return Response::make('Erreur générale: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Génère et télécharge un PDF de facture fournisseur
     *
     * @param string $supplierInvoiceId L'ID de la facture fournisseur à télécharger
     * @return mixed
     */
    public function downloadSupplierInvoice(string $supplierInvoiceId)
    {
        try {
            Log::info("[PdfGenerationController] Début de downloadSupplierInvoice pour ID: {$supplierInvoiceId}");
            $this->initializeTenantContext();

            try {
                // Vérifier si la facture fournisseur existe
                $supplierInvoiceExists = SupplierInvoice::where('id', $supplierInvoiceId)->exists();
                
                if (!$supplierInvoiceExists) {
                    Log::error("[PdfGenerationController] Facture fournisseur non trouvée ID: {$supplierInvoiceId}");
                    return Response::make('Facture fournisseur non trouvée.', 404);
                }
                
                // Charger la facture fournisseur avec ses relations
                $supplierInvoice = SupplierInvoice::with(['supplier', 'items', 'items.product', 'items.transactionUnit', 'user'])
                                      ->findOrFail($supplierInvoiceId);
                // Récupérer les informations de l'entreprise depuis les données du tenant
                $companyDetails = $this->getCompanyDetails();

                // Vérifier si le template existe
                if (!view()->exists('pdf.supplier-invoice')) {
                    Log::error("[PdfGenerationController] Template pdf.supplier-invoice n'existe pas");
                    return Response::make('Template PDF non disponible. Veuillez contacter l\'administrateur.', 500);
                }

                return Pdf::view('pdf.supplier-invoice', [
                            'supplierInvoice' => $supplierInvoice,
                            'company' => $companyDetails,
                        ])
                        ->format('a4')
                        ->name('Facture-Fournisseur-' . $supplierInvoice->invoice_number . '.pdf')
                        ->download();
                        
            } catch (\Exception $e) {
                Log::error("[PdfGenerationController] Erreur downloadSupplierInvoice ID: {$supplierInvoiceId} - " . $e->getMessage());
                Log::error("[PdfGenerationController] Trace: " . $e->getTraceAsString());
                return Response::make('Erreur lors de la génération du PDF de la facture fournisseur: ' . $e->getMessage(), 500);
            }
        } catch (\Exception $e) {
            Log::error("[PdfGenerationController] Erreur générale: " . $e->getMessage());
            return Response::make('Erreur générale: ' . $e->getMessage(), 500);
        }
    }
}