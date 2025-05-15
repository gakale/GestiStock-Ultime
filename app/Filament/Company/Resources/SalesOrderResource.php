<?php

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\SalesOrderResource\Pages;
// use App\Filament\Company\Resources\SalesOrderResource\RelationManagers;
use App\Models\SalesOrder;
use App\Models\Client;
use App\Models\Product;
use App\Models\Quotation; // Pour charger depuis un devis
use App\Models\Invoice;   // Pour la conversion
use App\Models\DeliveryNote; // Pour la conversion
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
use Filament\Notifications\Notification;

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
use Filament\Forms\Components\Actions\Action as FormAction;


// Table Columns
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;

class SalesOrderResource extends Resource
{
    protected static ?string $model = SalesOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Ventes';

    protected static ?int $navigationSort = 1; // Entre Devis et Factures/BL

    protected static ?string $recordTitleAttribute = 'order_number';

    public static array $statuses = [
        'pending_confirmation' => 'En attente de Confirmation',
        'confirmed' => 'Confirmée',
        'partially_shipped' => 'Partiellement Livrée',
        'fully_shipped' => 'Totalement Livrée',
        'partially_invoiced' => 'Partiellement Facturée',
        'fully_invoiced' => 'Totalement Facturée',
        'completed' => 'Terminée', // Livrée ET Facturée
        'cancelled' => 'Annulée',
    ];

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(3)->schema([
                    TextInput::make('order_number')
                        ->label('N° Bon de Commande')
                        ->default(fn() => SalesOrder::generateNextOrderNumber())
                        ->disabled()->dehydrated()->columnSpan(1),
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
                    DatePicker::make('order_date')
                        ->label('Date de Commande')
                        ->default(now())->required()->reactive(),
                    DatePicker::make('expected_shipment_date')
                        ->label('Date Expédition Prévue')
                        ->minDate(fn(Get $get) => Carbon::parse($get('order_date') ?? now())),
                    Select::make('status')
                        ->label('Statut')
                        ->options(self::$statuses)->default('pending_confirmation')->required(),
                ]),
                Grid::make(2)->schema([
                    Select::make('quotation_id')
                        ->label('Devis d\'origine (Optionnel)')
                        ->relationship('quotation', 'quotation_number', fn(Builder $query, Get $get) => $query->where('client_id', $get('client_id'))->where('status', 'accepted'))
                        ->searchable()->preload()
                        ->helperText('Affiche les devis acceptés pour le client sélectionné.'),
                    TextInput::make('client_po_reference')->label('Référence Commande Client'),
                ]),

                // Action pour charger depuis un devis (si quotation_id est sélectionné)
                Section::make()
                    ->schema([
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('loadFromQuotation')
                                ->label('Charger depuis le Devis')
                                ->icon('heroicon-o-document-arrow-down')
                                ->action(function (Get $get, Set $set) {
                                $quotationId = $get('quotation_id');
                                if ($quotationId) {
                                    $quotation = Quotation::with('items.product')->find($quotationId);
                                    if ($quotation) {
                                        $itemsData = [];
                                        foreach ($quotation->items as $qItem) {
                                            $itemsData[] = [
                                                'product_id' => $qItem->product_id,
                                                'product_name' => $qItem->product_name,
                                                'product_sku' => $qItem->product_sku,
                                                'description' => $qItem->description,
                                                'quantity_ordered' => $qItem->quantity,
                                                'quantity_shipped' => 0,
                                                'quantity_invoiced' => 0,
                                                'unit_price' => $qItem->unit_price,
                                                'discount_percentage' => $qItem->discount_percentage,
                                                'tax_rate' => $qItem->tax_rate,
                                                'line_total' => $qItem->line_total,
                                            ];
                                        }
                                        $set('items', $itemsData);
                                        // Pré-remplir les totaux du devis si le BC est vide
                                        $set('subtotal', $quotation->subtotal);
                                        $set('taxes_amount', $quotation->taxes_amount);
                                        $set('discount_amount', $quotation->discount_amount);
                                        $set('shipping_charges', $quotation->shipping_charges);
                                        $set('total_amount', $quotation->total_amount);
                                        // Appeler updateFormTotals pour rafraîchir les placeholders
                                        self::updateFormTotals($get, $set);
                                    }
                                }
                            })
                            ->disabled(fn (Get $get) => empty($get('quotation_id')))
                            ->requiresConfirmation(fn(Get $get) => !empty($get('items')) && count($get('items')) > 0, 'Attention', 'Charger un devis écrasera les lignes actuelles. Continuer ?')
                            ->visible(fn (Get $get) => !empty($get('quotation_id'))),
                        ]),
                    ])->columnSpanFull(),


                Section::make('Lignes de Commande')
                    ->schema([
                        Repeater::make('items')
                            ->label('Produits/Services Commandés')
                            ->relationship()
                            ->schema([ // Similaire à InvoiceResource et QuotationResource
                                Select::make('product_id')
                                    ->label('Produit')
                                    ->relationship('product', 'name', fn (Builder $query) => $query->where('is_active', true))
                                    ->searchable()->preload()->reactive()
                                    ->afterStateUpdated(function (Set $set, ?string $state, Get $get) {
                                        if ($state) {
                                            $product = Product::find($state);
                                            $set('unit_price', $product?->selling_price ?? 0);
                                            $set('product_name', $product?->name);
                                            $set('product_sku', $product?->sku);
                                            $set('description', $get('description') ?? $product?->description);
                                        } else { /* ... reset fields ... */ }
                                    })
                                    ->required()->columnSpan(3),
                                Hidden::make('product_name')->dehydrated(),
                                Hidden::make('product_sku')->dehydrated(),
                                Textarea::make('description')->label('Description Ligne')->rows(1)->columnSpanFull(),
                                TextInput::make('quantity_ordered')->label('Qté Cmdée')->integer()->required()->default(1)->minValue(1)->reactive()->columnSpan(1),
                                TextInput::make('unit_price')->label('P.U. HT')->numeric()->prefix('€')->required()->reactive()->columnSpan(1),
                                TextInput::make('discount_percentage')->label('Remise %')->numeric()->default(0)->minValue(0)->maxValue(100)->reactive()->columnSpan(1),
                                TextInput::make('tax_rate')->label('TVA %')->numeric()->default(20.00)->minValue(0)->maxValue(100)->reactive()->columnSpan(1),
                                Placeholder::make('line_total_display')->label('Total Ligne TTC')
                                    ->content(function (Get $get): string { /* ... calcul ... */
                                        $qty = (float)($get('quantity_ordered') ?? 0); // Utiliser quantity_ordered
                                        $price = (float)($get('unit_price') ?? 0);
                                        $discount = (float)($get('discount_percentage') ?? 0);
                                        $tax = (float)($get('tax_rate') ?? 0);
                                        $base = $qty * $price;
                                        $discountAmount = $base * ($discount / 100);
                                        $priceAfterDiscount = $base - $discountAmount;
                                        $taxAmount = $priceAfterDiscount * ($tax / 100);
                                        return number_format($priceAfterDiscount + $taxAmount, 2) . ' ' . config('app.currency_symbol', '€');
                                    })->columnSpan(1),
                                // Champs en lecture seule pour suivi
                                TextInput::make('quantity_shipped')->label('Qté Livrée')->integer()->disabled()->default(0)->columnSpan(1),
                                TextInput::make('quantity_invoiced')->label('Qté Facturée')->integer()->disabled()->default(0)->columnSpan(1),
                            ])
                            ->addActionLabel('Ajouter une ligne')->columns(7) // Ajuster colonnes
                            ->defaultItems(1)->reorderableWithButtons()->collapsible()
                            ->deleteAction(fn ($action) => $action->requiresConfirmation())
                            ->live(debounce: 300)
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::updateFormTotals($get, $set)),
                    ]),
                Section::make('Totaux et Adresse de Livraison')
                    ->schema([ // Similaire à QuotationResource
                        Grid::make(3)->schema([
                            Placeholder::make('subtotal_form_display')->label('Sous-total HT')->content(fn (Get $get): string => number_format((float)($get('subtotal_form_calculated') ?? 0), 2) . ' ' . config('app.currency_symbol', '€')),
                            Placeholder::make('taxes_amount_form_display')->label('Montant TVA')->content(fn (Get $get): string => number_format((float)($get('taxes_amount_form_calculated') ?? 0), 2) . ' ' . config('app.currency_symbol', '€')),
                            Placeholder::make('total_amount_form_display')->label('Total TTC')->content(fn (Get $get): string => number_format((float)($get('total_amount_form_calculated') ?? 0), 2) . ' ' . config('app.currency_symbol', '€')),
                        ]),
                        Hidden::make('subtotal')->dehydrated(), Hidden::make('taxes_amount')->dehydrated(), Hidden::make('total_amount')->dehydrated(),
                        Grid::make(2)->schema([
                            TextInput::make('shipping_charges')->label('Frais de port HT')->numeric()->prefix('€')->default(0)->reactive()->afterStateUpdated(fn (Get $get, Set $set) => self::updateFormTotals($get, $set)),
                            TextInput::make('discount_amount')->label('Remise globale HT')->numeric()->prefix('€')->default(0)->reactive()->afterStateUpdated(fn (Get $get, Set $set) => self::updateFormTotals($get, $set)),
                        ]),
                        Textarea::make('shipping_address_details')->label('Détails Adresse de Livraison')->rows(3)
                            ->helperText('Sera pré-remplie depuis l\'adresse de facturation du client si vide.'),
                    ]),
                Textarea::make('notes')->label('Notes Internes'),
            ])->columns(1);
    }

    // Réutiliser la méthode updateFormTotals (adapter si les noms de champs diffèrent, ex: quantity_ordered)
    public static function updateFormTotals(Get $get, Set $set): void
    {
        $items = $get('items') ?? [];
        $calculatedSubtotalAfterLineDiscounts = 0;
        $calculatedTotalTaxes = 0;

        foreach($items as $itemData) {
            $qty = (float)($itemData['quantity_ordered'] ?? 0); // Utiliser quantity_ordered
            $price = (float)($itemData['unit_price'] ?? 0);
            $discountPercent = (float)($itemData['discount_percentage'] ?? 0);
            $taxRate = (float)($itemData['tax_rate'] ?? 0);
            // ... (reste de la logique de calcul identique à QuotationResource/InvoiceResource)
            $lineBase = $qty * $price;
            $lineDiscountAmount = $lineBase * ($discountPercent / 100);
            $linePriceAfterDiscount = $lineBase - $lineDiscountAmount;
            $calculatedSubtotalAfterLineDiscounts += $linePriceAfterDiscount;
            $calculatedTotalTaxes += $linePriceAfterDiscount * ($taxRate / 100);
        }
        $globalDiscountAmount = (float)($get('discount_amount') ?? 0);
        $subtotalAfterGlobalDiscount = $calculatedSubtotalAfterLineDiscounts - $globalDiscountAmount;
        $shipping = (float)($get('shipping_charges') ?? 0);
        $finalTotal = $subtotalAfterGlobalDiscount + $calculatedTotalTaxes + $shipping;

        $set('subtotal_form_calculated', $calculatedSubtotalAfterLineDiscounts);
        $set('taxes_amount_form_calculated', $calculatedTotalTaxes);
        $set('total_amount_form_calculated', $finalTotal);
        $set('subtotal', $calculatedSubtotalAfterLineDiscounts);
        $set('taxes_amount', $calculatedTotalTaxes);
        $set('total_amount', $finalTotal);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')->label('N° BC')->searchable()->sortable(),
                TextColumn::make('client.display_name')->label('Client')->searchable()->sortable(),
                TextColumn::make('quotation.quotation_number')->label('Devis Origine')->searchable()->sortable()->placeholder('N/A'),
                TextColumn::make('order_date')->label('Date BC')->date()->sortable(),
                TextColumn::make('total_amount')->label('Total TTC')->money('eur')->sortable(),
                BadgeColumn::make('status')->label('Statut')->colors([ /* ... couleurs ... */ ])
                    ->formatStateUsing(fn (string $state): string => self::$statuses[$state] ?? ucfirst($state)),
            ])
            ->filters([ /* ... filtres ... */ ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('confirm_order')
                        ->label('Confirmer Commande')
                        ->icon('heroicon-s-check-badge')
                        ->action(fn (SalesOrder $record) => $record->update(['status' => 'confirmed']))
                        ->requiresConfirmation()->visible(fn (SalesOrder $record) => $record->status === 'pending_confirmation'),
                    // Action "Créer Bon de Livraison"
                    Tables\Actions\Action::make('create_delivery_note')
                        ->label('Créer Bon de Livraison')
                        ->icon('heroicon-s-truck')
                        ->action(function (SalesOrder $record) {
                            // Logique pour créer un DeliveryNote à partir du SalesOrder
                            // Vérifier les items non encore livrés
                            // Rediriger vers la page de création du DeliveryNote avec les données pré-remplies
                            $url = DeliveryNoteResource::getUrl('create', ['sales_order_id' => $record->id, 'client_id' => $record->client_id]);
                            return redirect($url);
                        })
                        ->visible(fn (SalesOrder $record) => in_array($record->status, ['confirmed', 'partially_shipped'])), //  && $record->hasItemsToShip()
                     // Action "Créer Facture"
                    Tables\Actions\Action::make('create_invoice')
                        ->label('Créer Facture')
                        ->icon('heroicon-s-document-text')
                        ->action(function (SalesOrder $record) {
                            // Logique pour créer une Invoice à partir du SalesOrder
                            $url = InvoiceResource::getUrl('create', ['sales_order_id' => $record->id, 'client_id' => $record->client_id]);
                            return redirect($url);
                        })
                        ->visible(fn (SalesOrder $record) => in_array($record->status, ['confirmed', 'partially_shipped', 'fully_shipped', 'partially_invoiced'])), // && $record->hasItemsToInvoice()
                ]),
            ])
            ->bulkActions([ /* ... */ ]);
    }

    // getRelations, getPages, getEloquentQuery similaires aux autres ressources
    public static function getRelations(): array { return []; }
    public static function getPages(): array {
        return [
            'index' => Pages\ListSalesOrders::route('/'),
            'create' => Pages\CreateSalesOrder::route('/create'),
            'edit' => Pages\EditSalesOrder::route('/{record}/edit'),
            'view' => Pages\ViewSalesOrder::route('/{record}'),
        ];
    }
    public static function getEloquentQuery(): Builder { /* ... */ return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class,]); }
}