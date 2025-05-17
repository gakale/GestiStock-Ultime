<?php

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\InvoiceResource\Pages;
// use App\Filament\Company\Resources\InvoiceResource\RelationManagers;
use App\Models\Invoice;
use App\Models\Client;
use App\Models\Product;
use App\Filament\Company\Resources\ClientResource;
// use App\Models\TenantUser; // Si vous avez besoin de lier l'utilisateur créateur explicitement au lieu du boot du modèle
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Carbon\Carbon; // Pour les dates

// Form Components
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;

// Table Columns
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Ventes'; // Nouveau groupe

    protected static ?int $navigationSort = 1; // Première ressource dans "Ventes"

    protected static ?string $recordTitleAttribute = 'invoice_number';

    public static array $statuses = [ // Rendre public ou utiliser une méthode pour y accéder si besoin ailleurs
        'draft' => 'Brouillon',
        'sent' => 'Envoyée',
        'partially_paid' => 'Partiellement Payée',
        'paid' => 'Payée',
        'overdue' => 'En Retard',
        'voided' => 'Annulée (Comptablement)', // Voided for accounting
        'cancelled' => 'Annulée (Avant envoi)', // Cancelled before it had an impact
    ];

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(3)->schema([
                    TextInput::make('invoice_number')
                        ->label('Numéro de Facture')
                        ->default(fn() => Invoice::generateNextInvoiceNumber())
                        ->disabled()
                        ->dehydrated()
                        ->columnSpan(1),
                    Select::make('client_id')
                        ->label('Client')
                        ->relationship(
                            name: 'client',
                            titleAttribute: 'company_name',
                            modifyQueryUsing: fn ($query) => $query
                        )
                        ->getOptionLabelFromRecordUsing(fn (Client $record) => $record->display_name)
                        ->searchable()
                        ->preload()
                        ->required()
                        ->createOptionForm([
                            Select::make('type')
                                ->label('Type de client')
                                ->options([
                                    'individual' => 'Particulier',
                                    'company' => 'Entreprise',
                                ])
                                ->required()
                                ->default('individual')
                                ->reactive(),
                            TextInput::make('company_name')
                                ->label('Nom de l\'entreprise')
                                ->visible(fn (Forms\Get $get) => $get('type') === 'company')
                                ->required(fn (Forms\Get $get) => $get('type') === 'company'),
                            Grid::make(2)->schema([
                                TextInput::make('first_name')->label('Prénom'),
                                TextInput::make('last_name')->label('Nom')->required(),
                            ]),
                            TextInput::make('email')->email()->label('Email'),
                            TextInput::make('phone_number')->tel()->label('Téléphone'),
                        ])
                        ->createOptionAction(fn (Forms\Components\Actions\Action $action) => $action->modalWidth('3xl'))
                        ->columnSpan(2),
                ]),
                Grid::make(3)->schema([
                    DatePicker::make('invoice_date')
                        ->label('Date de Facturation')
                        ->default(now())
                        ->required()
                        ->reactive(), // Pour que due_date se mette à jour
                    DatePicker::make('due_date')
                        ->label('Date d\'Échéance')
                        ->default(fn(Get $get) => Carbon::parse($get('invoice_date') ?? now())->addDays(30)->toDateString() )
                        ->minDate(fn(Get $get) => Carbon::parse($get('invoice_date') ?? now()))
                        ->required(),
                    Select::make('status')
                        ->label('Statut')
                        ->options(self::$statuses)
                        ->default('draft')
                        ->required(),
                ]),

                Section::make('Lignes de la Facture')
                    ->schema([
                        Repeater::make('items')
                            ->label('Produits/Services')
                            ->relationship()
                            ->schema([
                                Select::make('product_id')
                                    ->label('Produit')
                                    ->relationship('product', 'name', fn (Builder $query) => $query->where('is_active', true))
                                    ->searchable()
                                    ->preload()
                                    ->reactive()
                                    ->afterStateUpdated(function (Set $set, ?string $state, Get $get) {
                                        if ($state) {
                                            $product = Product::find($state);
                                            $set('unit_price', $product?->selling_price ?? 0);
                                            $set('product_name', $product?->name); // Copie
                                            $set('product_sku', $product?->sku);   // Copie
                                            // $set('description', $get('description') ?? $product?->description); // Pré-remplir description si vide
                                            // $set('tax_rate', $product?->default_tax_rate ?? 20.00); // Pré-remplir TVA
                                        } else { // Si le produit est déselectionné
                                            $set('unit_price', 0);
                                            $set('product_name', null);
                                            $set('product_sku', null);
                                        }
                                    })
                                    ->required()
                                    ->columnSpan(3),
                                Hidden::make('product_name')->dehydrated(), // Champs cachés pour stocker les copies
                                Hidden::make('product_sku')->dehydrated(),

                                Textarea::make('description')
                                    ->label('Description Ligne')
                                    ->rows(1)->columnSpanFull(),
                                TextInput::make('quantity')
                                    ->label('Qté')
                                    ->integer()
                                    ->required()
                                    ->default(1)
                                    ->minValue(1)
                                    ->reactive()
                                    ->columnSpan(1),
                                TextInput::make('unit_price')
                                    ->label('P.U. HT')
                                    ->numeric()
                                    ->prefix(config('app.currency_symbol', '€')) // Utiliser un symbole de devise configurable
                                    ->required()
                                    ->reactive()
                                    ->columnSpan(1),
                                TextInput::make('discount_percentage')
                                    ->label('Remise %')
                                    ->numeric()->default(0)->minValue(0)->maxValue(100)
                                    ->reactive()->columnSpan(1),
                                TextInput::make('tax_rate')
                                    ->label('TVA %')
                                    ->numeric()->default(20.00) // Taux de TVA par défaut pour la France, à rendre configurable
                                    ->minValue(0)->maxValue(100)
                                    ->reactive()->columnSpan(1),
                                Placeholder::make('line_total_display')
                                    ->label('Total Ligne TTC')
                                    ->content(function (Get $get): string {
                                        $qty = (float)($get('quantity') ?? 0);
                                        $price = (float)($get('unit_price') ?? 0);
                                        $discount = (float)($get('discount_percentage') ?? 0);
                                        $tax = (float)($get('tax_rate') ?? 0);
                                        $base = $qty * $price;
                                        $discountAmount = $base * ($discount / 100);
                                        $priceAfterDiscount = $base - $discountAmount;
                                        $taxAmount = $priceAfterDiscount * ($tax / 100);
                                        return number_format($priceAfterDiscount + $taxAmount, 2) . ' ' . config('app.currency_symbol', '€');
                                    })->columnSpan(1),

                            ])
                            ->addActionLabel('Ajouter une ligne')
                            ->columns(5)
                            ->defaultItems(1)
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->deleteAction(fn ($action) => $action->requiresConfirmation())
                            ->live(debounce: 300)
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::updateFormTotals($get, $set)),
                    ]),
                Section::make('Totaux et Paiement')
                    ->schema([
                        Grid::make(3)->schema([
                            Placeholder::make('subtotal_form_display')
                                ->label('Sous-total HT')
                                ->content(fn (Get $get): string => number_format((float)($get('subtotal_form_calculated') ?? 0), 2) . ' ' . config('app.currency_symbol', '€')),
                            Placeholder::make('taxes_amount_form_display')
                                ->label('Montant TVA')
                                ->content(fn (Get $get): string => number_format((float)($get('taxes_amount_form_calculated') ?? 0), 2) . ' ' . config('app.currency_symbol', '€')),
                            Placeholder::make('total_amount_form_display')
                                ->label('Total TTC')
                                ->content(fn (Get $get): string => number_format((float)($get('total_amount_form_calculated') ?? 0), 2) . ' ' . config('app.currency_symbol', '€')),
                        ]),
                        // Champs cachés pour les totaux globaux à sauvegarder (optionnel si le modèle recalcule tout)
                        // Hidden::make('subtotal')->dehydrated(),
                        // Hidden::make('taxes_amount')->dehydrated(),
                        // Hidden::make('total_amount')->dehydrated(),
                        Grid::make(2)->schema([
                            TextInput::make('shipping_charges')
                                ->label('Frais de port HT')
                                ->numeric()->prefix(config('app.currency_symbol', '€'))->default(0)->reactive()
                                ->afterStateUpdated(fn (Get $get, Set $set) => self::updateFormTotals($get, $set)),
                            TextInput::make('discount_amount') // Remise globale sur le total HT
                                ->label('Remise globale HT')
                                ->numeric()->prefix(config('app.currency_symbol', '€'))->default(0)->reactive()
                                ->afterStateUpdated(fn (Get $get, Set $set) => self::updateFormTotals($get, $set)),
                        ]),
                        TextInput::make('payment_terms')->label('Conditions de paiement')->placeholder('Ex: 30 jours net'),
                        TextInput::make('amount_paid')
                            ->label('Montant Payé')
                            ->numeric()
                            ->prefix(config('app.currency_symbol', '€'))
                            ->default(0),
                    ]),
                Textarea::make('notes_to_client')->label('Notes pour le Client (apparaitront sur la facture)'),
                Textarea::make('internal_notes')->label('Notes Internes (pour vous)'),

            ])->columns(1);
    }

    // Méthode pour calculer et mettre à jour les totaux dans le formulaire (pour l'UX)
    public static function updateFormTotals(Get $get, Set $set): void
    {
        $items = $get('items') ?? [];
        $calculatedSubtotalAfterLineDiscounts = 0; // Sous-total après remises de ligne, avant taxes de ligne
        $calculatedTotalTaxes = 0;

        foreach($items as $itemData) {
            $qty = (float)($itemData['quantity'] ?? 0);
            $price = (float)($itemData['unit_price'] ?? 0);
            $discountPercent = (float)($itemData['discount_percentage'] ?? 0);
            $taxRate = (float)($itemData['tax_rate'] ?? 0);

            $lineBase = $qty * $price;
            $lineDiscountAmount = $lineBase * ($discountPercent / 100);
            $linePriceAfterDiscount = $lineBase - $lineDiscountAmount;

            $calculatedSubtotalAfterLineDiscounts += $linePriceAfterDiscount;
            $calculatedTotalTaxes += $linePriceAfterDiscount * ($taxRate / 100);
        }

        // Appliquer la remise globale sur le sous-total (après remises de ligne)
        $globalDiscountAmount = (float)($get('discount_amount') ?? 0);
        $subtotalAfterGlobalDiscount = $calculatedSubtotalAfterLineDiscounts - $globalDiscountAmount;

        $shipping = (float)($get('shipping_charges') ?? 0);
        // Le total est : (Sous-total après toutes remises) + (Total Taxes Lignes) + Frais de Port
        $finalTotal = $subtotalAfterGlobalDiscount + $calculatedTotalTaxes + $shipping;

        $set('subtotal_form_calculated', $calculatedSubtotalAfterLineDiscounts); // Ce que l'on affiche comme sous-total
        $set('taxes_amount_form_calculated', $calculatedTotalTaxes);
        $set('total_amount_form_calculated', $finalTotal);

        // Mettre à jour les champs du modèle qui seront sauvegardés
        // Ces champs sont définis dans $fillable du modèle Invoice
        $set('subtotal', $calculatedSubtotalAfterLineDiscounts); // Sous-total avant taxes de lignes et avant remise globale
        $set('taxes_amount', $calculatedTotalTaxes);
        $set('discount_amount', $globalDiscountAmount); // Stocker la remise globale appliquée
        $set('shipping_charges', $shipping);
        $set('total_amount', $finalTotal);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_number')->label('N° Facture')->searchable()->sortable(),
                TextColumn::make('client.id')
                    ->label('Client')
                    ->formatStateUsing(fn ($state, $record) => $record->client?->display_name ?? '-')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('invoice_date')->label('Date Facture')->date()->sortable(),
                TextColumn::make('due_date')->label('Échéance')->date()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total_amount')->label('Total TTC')->money(config('app.currency', 'eur'))->sortable(),
                TextColumn::make('amount_paid')->label('Payé')->money(config('app.currency', 'eur'))->sortable(),
                 BadgeColumn::make('status')
                    ->label('Statut')
                    ->colors([
                        'gray' => 'draft',
                        'info' => 'sent',
                        'warning' => fn ($state) => $state === 'partially_paid' || $state === 'overdue',
                        'success' => 'paid',
                        'danger' => fn ($state) => $state === 'voided' || $state === 'cancelled',
                    ])
                    ->formatStateUsing(fn (string $state): string => self::$statuses[$state] ?? ucfirst($state)),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('client_id')
                    ->label('Client')
                    ->relationship(
                        name: 'client',
                        titleAttribute: 'company_name',
                        modifyQueryUsing: fn ($query) => $query
                    )
                    ->getOptionLabelFromRecordUsing(fn (Client $record) => $record->display_name)
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Statut')
                    ->options(self::$statuses),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
                // Plus tard: Action pour envoyer par email, marquer comme payé, etc.
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('invoice_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            // RelationManagers.PaymentRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
            'view' => Pages\ViewInvoice::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->orderBy('created_at', 'desc');
    }
}