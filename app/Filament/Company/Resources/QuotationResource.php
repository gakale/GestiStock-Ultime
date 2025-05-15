<?php

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\QuotationResource\Pages;
// use App\Filament\Company\Resources\QuotationResource\RelationManagers;
use App\Filament\Company\Resources\InvoiceResource;
use App\Models\Quotation;
use App\Models\Client;
use App\Models\Product;
use App\Models\Invoice; // Importer le modèle Invoice
use App\Models\InvoiceItem; // Importer le modèle InvoiceItem
use Filament\Notifications\Notification; // Pour les notifications
// use App\Models\TenantUser;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Carbon\Carbon;

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

class QuotationResource extends Resource
{
    protected static ?string $model = Quotation::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar'; // Icône pour devis

    protected static ?string $navigationGroup = 'Ventes';

    protected static ?int $navigationSort = 0; // Avant Factures

    protected static ?string $recordTitleAttribute = 'quotation_number';

    public static array $statuses = [
        'draft' => 'Brouillon',
        'sent' => 'Envoyé',
        'accepted' => 'Accepté',
        'declined' => 'Refusé',
        'expired' => 'Expiré',
    ];

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(3)->schema([
                    TextInput::make('quotation_number')
                        ->label('Numéro de Devis')
                        ->default(fn() => Quotation::generateNextQuotationNumber())
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
                        ->columnSpan(2),
                ]),
                Grid::make(3)->schema([
                    DatePicker::make('quotation_date')
                        ->label('Date du Devis')
                        ->default(now())
                        ->required()
                        ->reactive(),
                    DatePicker::make('expiry_date')
                        ->label('Date d\'Expiration')
                        ->default(fn(Get $get) => Carbon::parse($get('quotation_date') ?? now())->addDays(15)->toDateString() )
                        ->minDate(fn(Get $get) => Carbon::parse($get('quotation_date') ?? now()))
                        ->required(),
                    Select::make('status')
                        ->label('Statut')
                        ->options(self::$statuses)
                        ->default('draft')
                        ->required(),
                ]),

                Section::make('Lignes du Devis')
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
                                            $set('product_name', $product?->name);
                                            $set('product_sku', $product?->sku);
                                            $set('description', $get('description') ?? $product?->description); // Pré-remplir description
                                            // $set('tax_rate', $product?->default_tax_rate ?? 20.00); // Pré-remplir TVA si vous avez ça sur le produit
                                        } else {
                                            $set('unit_price', 0);
                                            $set('product_name', null);
                                            $set('product_sku', null);
                                        }
                                    })
                                    ->required()
                                    ->columnSpan(3),
                                Hidden::make('product_name')->dehydrated(),
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
                                    ->prefix(config('app.currency_symbol', '€'))
                                    ->required()
                                    ->reactive()
                                    ->columnSpan(1),
                                TextInput::make('discount_percentage')
                                    ->label('Remise %')
                                    ->numeric()->default(0)->minValue(0)->maxValue(100)
                                    ->reactive()->columnSpan(1),
                                TextInput::make('tax_rate')
                                    ->label('TVA %')
                                    ->numeric()->default(20.00)
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
                Section::make('Totaux et Conditions')
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
                         // Champs cachés pour les totaux globaux à sauvegarder
                        Hidden::make('subtotal')->dehydrated(),
                        Hidden::make('taxes_amount')->dehydrated(),
                        Hidden::make('total_amount')->dehydrated(),
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
                        Textarea::make('terms_and_conditions')->label('Termes et Conditions du Devis')->rows(3),
                    ]),
                Textarea::make('notes_to_client')->label('Notes pour le Client (apparaitront sur le devis)'),
                Textarea::make('internal_notes')->label('Notes Internes (pour vous)'),

            ])->columns(1);
    }

    // Méthode pour calculer et mettre à jour les totaux dans le formulaire (pour l'UX)
    // Réutilisée depuis InvoiceResource, assurez-vous que les noms des champs sont cohérents
    public static function updateFormTotals(Get $get, Set $set): void
    {
        $items = $get('items') ?? [];
        $calculatedSubtotalAfterLineDiscounts = 0;
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

        $globalDiscountAmount = (float)($get('discount_amount') ?? 0); // Remise globale
        $subtotalAfterGlobalDiscount = $calculatedSubtotalAfterLineDiscounts - $globalDiscountAmount;

        $shipping = (float)($get('shipping_charges') ?? 0);
        $finalTotal = $subtotalAfterGlobalDiscount + $calculatedTotalTaxes + $shipping;

        // Pour l'affichage
        $set('subtotal_form_calculated', $calculatedSubtotalAfterLineDiscounts);
        $set('taxes_amount_form_calculated', $calculatedTotalTaxes);
        $set('total_amount_form_calculated', $finalTotal);

        // Pour la sauvegarde dans le modèle Quotation
        $set('subtotal', $calculatedSubtotalAfterLineDiscounts);
        $set('taxes_amount', $calculatedTotalTaxes);
        // $set('discount_amount', $globalDiscountAmount); // 'discount_amount' est déjà un champ du formulaire principal
        $set('shipping_charges', $shipping);
        $set('total_amount', $finalTotal);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('quotation_number')->label('N° Devis')->searchable()->sortable(),
                TextColumn::make('client.id')
                    ->label('Client')
                    ->formatStateUsing(fn ($state, $record) => $record->client?->display_name ?? '-')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('quotation_date')->label('Date Devis')->date()->sortable(),
                TextColumn::make('expiry_date')->label('Expiration')->date()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total_amount')->label('Total TTC')->money(config('app.currency', 'eur'))->sortable(),
                BadgeColumn::make('status')
                    ->label('Statut')
                    ->colors([
                        'gray' => 'draft',
                        'info' => 'sent',
                        'success' => 'accepted',
                        'danger' => fn ($state) => $state === 'declined' || $state === 'expired',
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
                Tables\Filters\TrashedFilter::make(), // Si vous utilisez SoftDeletes pour Quotation
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
                // Actions à ajouter: "Marquer comme Envoyé", "Convertir en Facture", etc.
                Tables\Actions\ActionGroup::make([
                    // Exemple d'action de changement de statut
                    Tables\Actions\Action::make('mark_as_sent')
                        ->label('Marquer comme Envoyé')
                        ->icon('heroicon-s-paper-airplane')
                        ->action(fn (Quotation $record) => $record->update(['status' => 'sent']))
                        ->requiresConfirmation()
                        ->visible(fn (Quotation $record) => $record->status === 'draft'),
                    Tables\Actions\Action::make('mark_as_accepted')
                        ->label('Marquer comme Accepté')
                        ->icon('heroicon-s-check-circle')
                        ->color('success')
                        ->action(fn (Quotation $record) => $record->update(['status' => 'accepted']))
                        ->requiresConfirmation()
                        ->visible(fn (Quotation $record) => $record->status === 'sent'),
                    Tables\Actions\Action::make('convert_to_invoice')
                        ->label('Convertir en Facture')
                        ->icon('heroicon-s-document-duplicate')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Convertir le Devis en Facture')
                        ->modalDescription('Êtes-vous sûr de vouloir créer une nouvelle facture basée sur ce devis ?')
                        ->action(function (Quotation $record) {
                            if ($record->status !== 'accepted') {
                                Notification::make()
                                    ->title('Conversion impossible')
                                    ->body('Le devis doit être au statut "Accepté" pour être converti en facture.')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            // Vérifier si une facture a déjà été générée pour ce devis (pour éviter les doublons)
                            // Vous pourriez ajouter une colonne `converted_to_invoice_id` sur le modèle Quotation
                            // ou une relation `invoice()` sur Quotation qui cherche une facture avec `source_document_id = $record->id`
                            // Pour l'instant, on ne fait pas cette vérification.

                            $invoiceData = [
                                'client_id' => $record->client_id,
                                'user_id' => auth()->check() && auth()->user() instanceof \App\Models\TenantUser ? auth()->user()->getKey() : $record->user_id,
                                'invoice_date' => now()->toDateString(),
                                'due_date' => now()->addDays(30)->toDateString(), // Ou basé sur les termes du client/devis
                                'status' => 'draft', // La nouvelle facture est créée en brouillon
                                'subtotal' => $record->subtotal,
                                'taxes_amount' => $record->taxes_amount,
                                'discount_amount' => $record->discount_amount,
                                'shipping_charges' => $record->shipping_charges,
                                'total_amount' => $record->total_amount,
                                'payment_terms' => $record->terms_and_conditions, // Ou des termes spécifiques aux factures
                                'notes_to_client' => $record->notes_to_client,
                                // Lier la facture au devis source (ajoutez ces colonnes à la table invoices si ce n'est pas fait)
                                'source_document_type' => Quotation::class,
                                'source_document_id' => $record->id,
                            ];

                            $invoice = Invoice::create($invoiceData);

                            foreach ($record->items as $quotationItem) {
                                InvoiceItem::create([
                                    'invoice_id' => $invoice->id,
                                    'product_id' => $quotationItem->product_id,
                                    'product_name' => $quotationItem->product_name,
                                    'product_sku' => $quotationItem->product_sku,
                                    'description' => $quotationItem->description,
                                    'quantity' => $quotationItem->quantity,
                                    'unit_price' => $quotationItem->unit_price,
                                    'discount_percentage' => $quotationItem->discount_percentage,
                                    'tax_rate' => $quotationItem->tax_rate,
                                    'line_total' => $quotationItem->line_total, // Recalculé par le modèle InvoiceItem de toute façon
                                ]);
                            }
                            // Optionnel: Mettre à jour le statut du devis (ex: 'converted_to_invoice')
                            $record->update(['status' => 'accepted']); // Ou un autre statut comme 'converted_to_invoice' si vous l'ajoutez

                            Notification::make()
                                ->title('Facture créée')
                                ->body("La facture {$invoice->invoice_number} a été créée avec succès.")
                                ->success()
                                ->send();

                            // Rediriger vers la page d'édition de la nouvelle facture
                            return redirect(InvoiceResource::getUrl('edit', ['record' => $invoice]));
                        })
                        ->visible(fn (Quotation $record) => $record->status === 'accepted'), // Visible seulement si le devis est accepté
                    // ... autres actions de statut
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    // Tables\Actions\ForceDeleteBulkAction::make(),
                    // Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQuotations::route('/'),
            'create' => Pages\CreateQuotation::route('/create'),
            'edit' => Pages\EditQuotation::route('/{record}/edit'),
            'view' => Pages\ViewQuotation::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class, // Si vous utilisez SoftDeletes
            ]);
    }
}