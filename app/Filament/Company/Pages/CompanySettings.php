<?php

declare(strict_types=1);

namespace App\Filament\Company\Pages;

use Filament\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Stancl\Tenancy\Database\Models\Domain;
use Illuminate\Support\Facades\Log;

class CompanySettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationGroup = 'Configuration';
    protected static string $view = 'filament.company.pages.company-settings';
    protected static ?string $title = 'Informations de l\'Entreprise';
    protected static ?string $navigationLabel = 'Mon Entreprise';
    protected static ?int $navigationSort = 1;

    // Propriétés pour stocker les données du formulaire et l'ID du tenant
    public ?array $data = [];
    public ?string $tenantId = null;
    
    /**
     * Récupère le tenant actuel de manière robuste
     * 
     * @return Tenant|null
     */
    protected function getTenant(): ?Tenant
    {
        // Si nous avons déjà un ID de tenant stocké, l'utiliser
        if ($this->tenantId) {
            return Tenant::find($this->tenantId);
        }
        
        // Essayer d'abord la fonction helper tenant()
        $tenant = tenant();
        if ($tenant) {
            $this->tenantId = $tenant->id;
            return $tenant;
        }
        
        // Essayer de récupérer le tenant à partir du domaine actuel
        $domain = request()->getHost();
        $tenantDomain = Domain::where('domain', $domain)->first();
        if ($tenantDomain) {
            $tenant = $tenantDomain->tenant;
            $this->tenantId = $tenant->id;
            return $tenant;
        }
        
        // Journaliser l'erreur pour le débogage
        Log::error("[CompanySettings] Impossible de déterminer le tenant pour l'utilisateur ID: " . Auth::id());
        
        return null;
    }

    public function mount(): void
    {
        // Charger les données existantes du tenant actuel
        $tenant = $this->getTenant();
        
        if ($tenant) {
            // Récupérer les données du tenant
            $tenantData = $tenant->data ?? [];
            
            // Mettre à jour la propriété $data
            $this->data = $tenantData;
            
            // Remplir le formulaire avec les données
            $this->form->fill($tenantData);
            
            // Log pour débogage
            Log::info("[CompanySettings] Données chargées pour le tenant ID: {$tenant->id}", ['data' => $tenantData]);
        } else {
            // Si aucun tenant n'est trouvé, initialiser avec un tableau vide
            $this->data = [];
            $this->form->fill([]);
            
            // Afficher une notification d'erreur
            Notification::make()
                ->title('Erreur de configuration')
                ->body('Impossible de déterminer le tenant actuel. Certaines fonctionnalités pourraient ne pas fonctionner correctement.')
                ->warning()
                ->persistent()
                ->send();
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Identité de l\'Entreprise')
                    ->schema([
                        TextInput::make('company_legal_name')->label('Raison Sociale Complète')->required(),
                        TextInput::make('company_vat_number')->label('N° TVA / SIRET'),
                        TextInput::make('company_website')->label('Site Web')->url()->nullable(),
                        FileUpload::make('company_logo_path')
                            ->label('Logo de l\'entreprise (pour PDF)')
                            ->disk('public') // Utiliser le disque public
                            ->directory('tenant_logos') // Dossier simple pour tous les tenants
                            ->visibility('public') // S'assurer que les fichiers sont accessibles publiquement
                            ->image() // Accepter uniquement les images
                            ->maxSize(2048) // Limiter la taille à 2Mo
                            ->imageResizeMode('contain') // Mode de redimensionnement
                            ->imageCropAspectRatio('16:9') // Ratio d'aspect pour le recadrage
                            ->imageResizeTargetWidth('1024') // Largeur cible après redimensionnement
                            ->imageResizeTargetHeight('768') // Hauteur cible après redimensionnement
                            ->helperText('Format recommandé : PNG ou JPG. Max 2Mo.')
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Coordonnées')
                    ->schema([
                        TextInput::make('company_address_line1')->label('Adresse (Ligne 1)'),
                        TextInput::make('company_address_line2')->label('Adresse (Ligne 2)'),
                        TextInput::make('company_postal_code')->label('Code Postal'),
                        TextInput::make('company_city')->label('Ville'),
                        TextInput::make('company_country')->label('Pays'),
                        TextInput::make('company_phone')->label('Téléphone Principal')->tel(),
                        TextInput::make('company_email')->label('Email de Contact Principal')->email(),
                    ])->columns(2),

                Section::make('Informations Bancaires (pour factures)')
                    ->schema([
                        Textarea::make('company_bank_details')
                            ->label('Détails Bancaires (IBAN, BIC, Nom Banque)')
                            ->rows(3)
                            ->helperText('Sera affiché sur vos factures.'),
                    ])->columns(1),
                
                Section::make('Paramètres des Documents')
                    ->schema([
                        Textarea::make('invoice_payment_terms')
                            ->label('Conditions de Paiement par Défaut (Factures)')
                            ->rows(2)
                            ->helperText('Ex: Paiement à 30 jours net.'),
                        Textarea::make('invoice_footer_notes')
                            ->label('Notes de Pied de Page par Défaut (Factures)')
                            ->rows(3)
                            ->helperText('Ex: Informations légales, messages promotionnels...'),
                    ])->columns(1),
            ])
            ->statePath('data'); // Important: lie le formulaire à la propriété $data
    }

    public function save(): void
    {
        try {
            // Récupérer le tenant de manière robuste
            $tenant = $this->getTenant();
            
            // Vérifier si le tenant existe
            if (!$tenant) {
                throw new \Exception('Impossible de déterminer le tenant actuel. Veuillez vous reconnecter et réessayer.');
            }
            
            // Log pour débogage
            Log::info("[CompanySettings] Sauvegarde des données pour le tenant ID: {$tenant->id}");
            
            // Récupérer les données actuelles ou initialiser un tableau vide
            $currentData = $tenant->data ?? [];
            $newData = $this->form->getState();
            
            // Log des données pour débogage
            Log::info("[CompanySettings] Données actuelles: ", ['data' => $currentData]);
            Log::info("[CompanySettings] Nouvelles données: ", ['data' => $newData]);
            
            // Traitement spécial pour le logo (Filament stocke un tableau pour les fichiers téléchargés)
            if (isset($newData['company_logo_path']) && is_array($newData['company_logo_path'])) {
                // Si c'est un tableau et qu'il contient au moins un élément
                if (!empty($newData['company_logo_path'])) {
                    // Prendre le premier élément du tableau (chemin du fichier)
                    $newData['company_logo_path'] = $newData['company_logo_path'][0] ?? null;
                    Log::info("[CompanySettings] Chemin du logo traité: {$newData['company_logo_path']}");
                } else {
                    // Si le tableau est vide, conserver l'ancienne valeur s'il y en a une
                    $newData['company_logo_path'] = $currentData['company_logo_path'] ?? null;
                }
            }
            
            // Fusionner avec les nouvelles données du formulaire
            $tenant->data = array_merge($currentData, $newData);
            
            // Sauvegarder les modifications
            $tenant->save();
            
            // Mettre à jour la propriété $data avec les nouvelles données
            $this->data = $tenant->data;
            
            // Recharger le formulaire avec les données mises à jour
            $this->form->fill($tenant->data);
            
            // Log de confirmation
            Log::info("[CompanySettings] Données sauvegardées avec succès pour le tenant ID: {$tenant->id}");

            Notification::make()
                ->title('Informations de l\'entreprise sauvegardées avec succès')
                ->success()
                ->send();
        } catch (\Exception $e) {
            // Log de l'erreur
            Log::error("[CompanySettings] Erreur lors de la sauvegarde: " . $e->getMessage());
            Log::error("[CompanySettings] Trace: " . $e->getTraceAsString());
            
            Notification::make()
                ->title('Erreur lors de la sauvegarde')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
