<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\CreditNote;
use Illuminate\Http\Request;
use Spatie\LaravelPdf\Facades\Pdf; // Utiliser la Facade
use Illuminate\Support\Facades\App; // Pour le contexte du tenant
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

class PdfGenerationController extends Controller
{
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
                
                // Informations de l'entreprise simplifiées pour le débogage
                $companyDetails = [
                    'name' => tenant('name') ?? 'Votre Entreprise',
                    'address' => 'Adresse de l\'entreprise',
                    'city' => 'Ville de l\'entreprise',
                    'phone' => 'Téléphone de l\'entreprise',
                    'email' => 'Email de l\'entreprise',
                    'vat_number' => 'Numéro TVA de l\'entreprise',
                ];
                
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
                
                // Informations de l'entreprise simplifiées pour le débogage
                $companyDetails = [
                    'name' => tenant('name') ?? 'Votre Entreprise',
                    'address' => 'Adresse de l\'entreprise',
                    'city' => 'Ville de l\'entreprise',
                    'phone' => 'Téléphone de l\'entreprise',
                    'email' => 'Email de l\'entreprise',
                    'vat_number' => 'Numéro TVA de l\'entreprise',
                ];
                
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
}