<?php

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\DeliveryNoteResource\Pages;
// use App\Filament\Company\Resources\DeliveryNoteResource\RelationManagers;
use App\Models\DeliveryNote;
use App\Models\Client;
use App\Models\Product;
use App\Models\Invoice; // Pour charger depuis une facture
// use App\Models\SalesOrder; // Plus tard, pour charger depuis un Bon de Commande Client
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope; // Si vous l'utilisez sur DeliveryNote
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
use Filament\Forms\Components\Actions;

// Table Columns
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;

class DeliveryNoteResource extends Resource
{
    protected static ?string $model = DeliveryNote::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck'; // Même que Fournisseur, ou un autre comme 'paper-airplane'

    protected static ?string $navigationGroup = 'Ventes';

    protected static ?int $navigationSort = 2; // Après Factures

    protected static ?string $recordTitleAttribute = 'delivery_note_number';

    public static array $statuses = [
        'draft' => 'Brouillon',
        'ready_to_ship' => 'Prêt à Expédier',
        'shipped' => 'Expédié', // Ce statut (ou delivered) déclenchera la sortie de stock
        'delivered' => 'Livré',   // Ce statut aussi
        'cancelled' => 'Annulé',
    ];

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(3)->schema([
                    TextInput::make('delivery_note_number')
                        ->label('N° Bon de Livraison')
                        ->default(fn() => DeliveryNote::generateNextDeliveryNoteNumber())
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
                        ->reactive()
                        ->columnSpan(2),
                ]),
                Grid::make(3)->schema([
                    DatePicker::make('delivery_date')
                        ->label('Date de Livraison')
                        ->default(now())
                        ->required(),
                    Select::make('invoice_id') // Source: Facture
                        ->label('Facture Associée (Optionnel)')
                        ->options(function (Get $get): array {
                            $clientId = $get('client_id');
                            if ($clientId) {
                                // Afficher les factures non (totalement) livrées ou pertinentes
                                return Invoice::where('client_id', $clientId)
                                    // ->whereNotIn('status', ['voided', 'cancelled']) // Exclure certains statuts de facture
                                    ->orderBy('invoice_date', 'desc')
                                    ->limit(50) // Limiter pour la performance
                                    ->pluck('invoice_number', 'id')
                                    ->all();
                            }
                            return [];
                        })
                        ->searchable()
                        ->reactive()
                        ->columnSpan(1),
                    // Plus tard, on ajoutera SalesOrder ici
                    Select::make('status')
                        ->label('Statut du BL')
                        ->options(self::$statuses)
                        ->default('draft')
                        ->required()
                        ->columnSpan(1),
                ]),

                Section::make()
                    ->schema([
                        Actions::make([
                            Actions\Action::make('loadFromInvoice')
                                ->label('Charger depuis la Facture')
                                ->icon('heroicon-o-document-duplicate')
                                ->action(function (Get $get, Set $set) {
                                $invoiceId = $get('invoice_id');
                                if ($invoiceId) {
                                    $invoice = Invoice::with('items.product')->find($invoiceId);
                                    if ($invoice) {
                                        $itemsData = [];
                                        foreach ($invoice->items as $invItem) {
                                            // Calculer la quantité déjà livrée pour cet item de facture
                                            $totalShippedForInvoiceItem = \App\Models\DeliveryNoteItem::where('invoice_item_id', $invItem->id)
                                                                                                ->sum('quantity_shipped');
                                            $quantityToShip = $invItem->quantity - $totalShippedForInvoiceItem;

                                            if ($quantityToShip > 0) {
                                                $itemsData[] = [
                                                    'product_id' => $invItem->product_id,
                                                    'invoice_item_id' => $invItem->id,
                                                    'product_name' => $invItem->product_name, // Copier
                                                    'product_sku' => $invItem->product_sku,   // Copier
                                                    'quantity_ordered' => $invItem->quantity, // Qté sur la facture
                                                    'quantity_shipped' => $quantityToShip, // Pré-remplir avec qté restante
                                                ];
                                            }
                                        }
                                        $set('items', $itemsData);
                                        if(empty($get('client_id'))) { // Mettre à jour client si pas déjà fait
                                            $set('client_id', $invoice->client_id);
                                        }
                                        // Pré-remplir l'adresse de livraison depuis le client si vide
                                        if (empty($get('shipping_address_line1')) && $invoice->client) {
                                            $client = $invoice->client;
                                            $set('shipping_address_line1', $client->billing_address_line1);
                                            $set('shipping_address_line2', $client->billing_address_line2);
                                            $set('shipping_city', $client->billing_city);
                                            $set('shipping_postal_code', $client->billing_postal_code);
                                            $set('shipping_country', $client->billing_country);
                                        }
                                    }
                                }
                            })
                            ->disabled(fn (Get $get) => empty($get('invoice_id')))
                            ->requiresConfirmation(fn(Get $get) => !empty($get('items')) && count($get('items')) > 0, 'Attention', 'Charger depuis la facture écrasera les lignes actuelles. Continuer ?')
                            ->modalHeading('Confirmer le chargement')
                            ->modalSubmitActionLabel('Charger')
                            ->visible(fn (Get $get) => !empty($get('invoice_id'))),
                        ]),
                    ])->columnSpanFull(),

                Section::make('Lignes de Livraison')
                    ->schema([
                        Repeater::make('items')
                            ->label('Produits à Livrer')
                            ->relationship()
                            ->schema([
                                Select::make('product_id')
                                    ->label('Produit')
                                    ->options(Product::query()->where('is_active', true)->pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function (Set $set, ?string $state, Get $get) {
                                        if ($state && !$get('invoice_item_id')) { // Ne pas écraser si chargé depuis facture
                                            $product = Product::find($state);
                                            $set('product_name', $product?->name);
                                            $set('product_sku', $product?->sku);
                                        }
                                    })
                                    ->columnSpan(2),
                                Hidden::make('product_name')->dehydrated(),
                                Hidden::make('product_sku')->dehydrated(),
                                TextInput::make('quantity_ordered')
                                    ->label('Qté Cmdée/Fact.')
                                    ->integer()
                                    ->disabled() // Non modifiable, vient de la source (facture/BC)
                                    ->columnSpan(1),
                                TextInput::make('quantity_shipped')
                                    ->label('Qté Livrée')
                                    ->integer()
                                    ->required()
                                    ->minValue(0)
                                    // ->maxValue(fn (Get $get) => (int) $get('quantity_ordered')) // Assurer qu'on ne livre pas plus que commandé
                                    ->columnSpan(1),
                                Hidden::make('invoice_item_id'),
                                // Hidden::make('sales_order_item_id'),
                            ])
                            ->addActionLabel('Ajouter une ligne manuellement')
                            ->columns(4) // Ajuster le nombre de colonnes
                            ->defaultItems(0)
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->deleteAction(fn ($action) => $action->requiresConfirmation()),
                    ]),
                Section::make('Informations de Livraison')
                    ->schema([
                        Grid::make(2)->schema([
                            Textarea::make('shipping_address_line1')->label('Adresse Livraison Ligne 1')->rows(2),
                            Textarea::make('shipping_address_line2')->label('Adresse Livraison Ligne 2')->rows(2),
                        ]),
                        Grid::make(3)->schema([
                            TextInput::make('shipping_postal_code')->label('Code Postal Livraison'),
                            TextInput::make('shipping_city')->label('Ville Livraison'),
                            TextInput::make('shipping_country')->label('Pays Livraison'),
                        ]),
                         Grid::make(2)->schema([
                            TextInput::make('carrier_name')->label('Transporteur'),
                            TextInput::make('tracking_number')->label('N° de Suivi Colis'),
                        ]),
                    ]),
                Textarea::make('notes')->label('Notes pour la livraison'),
            ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('delivery_note_number')->label('N° BL')->searchable()->sortable(),
                TextColumn::make('client.id')
                    ->label('Client')
                    ->formatStateUsing(fn ($state, $record) => $record->client?->display_name ?? '-')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('invoice.invoice_number')->label('Facture Associée')->searchable()->sortable()->placeholder('N/A'),
                TextColumn::make('delivery_date')->label('Date Livraison')->date()->sortable(),
                BadgeColumn::make('status')
                    ->label('Statut')
                    ->colors([
                        'gray' => 'draft',
                        'warning' => 'ready_to_ship',
                        'info' => 'shipped',
                        'success' => 'delivered',
                        'danger' => 'cancelled',
                    ])
                    ->formatStateUsing(fn (string $state): string => self::$statuses[$state] ?? ucfirst($state)),
                TextColumn::make('created_at')->label('Créé le')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Filtres à ajouter
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
        // Si vous avez utilisé --simple, Filament gère cela avec une page Manage.
        // Sinon, vous auriez Create, Edit, View, List.
        return [
            'index' => Pages\ListDeliveryNotes::route('/'),
            'create' => Pages\CreateDeliveryNote::route('/create'),
            'edit' => Pages\EditDeliveryNote::route('/{record}/edit'),
            'view' => Pages\ViewDeliveryNote::route('/{record}'),
        ];
    }
}