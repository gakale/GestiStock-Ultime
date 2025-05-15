<?php

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\GoodsReceiptResource\Pages;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem; // Add this import
use App\Models\PurchaseOrder;
use App\Models\Product;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Get;
use Filament\Forms\Set;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Textarea;
// Remove the incorrect import: use Filament\Forms\Components\Actions\Action as FormAction;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;


class GoodsReceiptResource extends Resource
{
    protected static ?string $model = GoodsReceipt::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box-arrow-down';

    protected static ?string $navigationGroup = 'Achats';
    protected static ?int $navigationSort = 2; // Après Commandes Fournisseurs

    protected static ?string $recordTitleAttribute = 'receipt_number';

    public static $statuses = [
        'pending_quality_check' => 'En attente contrôle qualité',
        'completed' => 'Terminée',
        'cancelled' => 'Annulée',
    ];

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(3)->schema([
                    TextInput::make('receipt_number')
                        ->label('N° Bon de Réception')
                        ->default(fn() => GoodsReceipt::generateNextReceiptNumber())
                        ->disabled()
                        ->dehydrated()
                        ->columnSpan(1),
                    Select::make('supplier_id')
                        ->label('Fournisseur')
                        ->relationship('supplier', 'company_name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->reactive() // Pour filtrer les commandes fournisseurs
                        ->columnSpan(2),
                ]),
                Grid::make(3)->schema([
                    DatePicker::make('receipt_date')
                        ->label('Date de Réception')
                        ->default(now())
                        ->required(),
                    Select::make('purchase_order_id')
                        ->label('Commande Fournisseur (Optionnel)')
                        ->options(function (Get $get) {
                            $supplierId = $get('supplier_id');
                            if ($supplierId) {
                                // Afficher seulement les commandes non totalement reçues ou en attente
                                return PurchaseOrder::where('supplier_id', $supplierId)
                                    ->whereIn('status', ['ordered', 'partially_received', 'approved'])
                                    ->pluck('order_number', 'id');
                            }
                            return [];
                        })
                        ->searchable()
                        ->reactive() // Pour charger les items
                        ->columnSpan(1),
                    // Bouton pour charger les items de la commande sélectionnée
                    Forms\Components\Placeholder::make('load_po_items_button')
                        ->label('')
                        ->content('') // On va juste mettre un bouton
                        ->columnSpan(1)
                        ->visible(fn (Get $get) => !empty($get('purchase_order_id'))),
                ])->columnSpanFull() // Le Grid prendra toute la largeur disponible
                ->columns(3), // S'assurer que le grid a bien 3 colonnes

                // Le bouton sera mieux placé dans une action de header du repeater ou une action de formulaire
                Section::make()
                    ->schema([
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('loadFromPurchaseOrder')
                                ->label('Charger depuis la Commande Fournisseur')
                                ->icon('heroicon-o-arrow-down-tray')
                                ->action(function (Get $get, Set $set) {
                                    $purchaseOrderId = $get('purchase_order_id');
                                    if ($purchaseOrderId) {
                                        $po = PurchaseOrder::with('items.product')->find($purchaseOrderId);
                                        if ($po) {
                                            $itemsData = [];
                                            foreach ($po->items as $poItem) {
                                                // Calculer la quantité restante à recevoir
                                                $totalReceivedForPoItem = \App\Models\GoodsReceiptItem::where('purchase_order_item_id', $poItem->id)
                                                                                        ->sum('quantity_received');
                                                $quantityToReceive = $poItem->quantity - $totalReceivedForPoItem;

                                                if ($quantityToReceive > 0) {
                                                    $itemsData[] = [
                                                        'product_id' => $poItem->product_id,
                                                        'purchase_order_item_id' => $poItem->id,
                                                        'quantity_ordered' => $poItem->quantity,
                                                        'quantity_received' => $quantityToReceive, // Pré-remplir avec la qté restante
                                                        'unit_price' => $poItem->unit_price,
                                                    ];
                                                }
                                            }
                                            $set('items', $itemsData);
                                            // Mettre à jour le fournisseur si non déjà fait
                                            if(empty($get('supplier_id'))) {
                                                $set('supplier_id', $po->supplier_id);
                                            }
                                        }
                                    }
                                })
                                ->disabled(fn (Get $get) => empty($get('purchase_order_id')))
                                ->requiresConfirmation()
                                ->modalHeading('Attention')
                                ->modalDescription('Charger une nouvelle commande écrasera les lignes actuelles. Continuer ?')
                                ->modalSubmitActionLabel('Confirmer')
                                ->visible(fn (Get $get) => !empty($get('purchase_order_id'))),
                        ]),
                    ])->columnSpanFull(),


                Section::make('Lignes de Réception')
                    ->schema([
                        Repeater::make('items')
                            ->label('Produits Reçus')
                            ->relationship()
                            ->schema([
                                Select::make('product_id')
                                    ->label('Produit')
                                    ->options(Product::query()->pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function (Set $set, ?string $state, Get $get) {
                                        if ($state && !$get('purchase_order_item_id')) { // Ne pas écraser si chargé depuis PO
                                            $product = Product::find($state);
                                            $set('unit_price', $product?->purchase_price ?? $product?->selling_price ?? 0);
                                        }
                                    })
                                    ->columnSpan(2),
                                TextInput::make('quantity_ordered')
                                    ->label('Qté Cmdée')
                                    ->integer()
                                    ->disabled() // Non modifiable, vient de la PO
                                    ->columnSpan(1),
                                TextInput::make('quantity_received')
                                    ->label('Qté Reçue')
                                    ->integer()
                                    ->required()
                                    ->minValue(0) // Peut recevoir 0 si un produit est en rupture
                                    ->columnSpan(1),
                                TextInput::make('unit_price')
                                    ->label('Prix Unitaire Réception')
                                    ->numeric()
                                    ->prefix('€')
                                    ->required()
                                    ->columnSpan(1),
                                Hidden::make('purchase_order_item_id'), // Pour garder le lien
                            ])
                            ->addActionLabel('Ajouter une ligne manuellement')
                            ->columns(5)
                            ->defaultItems(0) // Commencer sans ligne par défaut
                            ->reorderableWithButtons()
                            ->collapsible()
                            // Simplified deleteAction without type hinting
                            ->deleteAction(
                                fn ($action) => $action->requiresConfirmation()
                            ),
                    ]),
                Grid::make(1)->schema([
                    TextInput::make('supplier_delivery_note_number')->label('N° BL Fournisseur'),
                    Select::make('status')
                        ->label('Statut de la Réception')
                        ->options(self::$statuses)
                        ->default('completed')
                        ->required(),
                    Textarea::make('notes')->label('Notes'),
                ]),
            ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('receipt_number')->label('N° Réception')->searchable()->sortable(),
                TextColumn::make('purchaseOrder.order_number')->label('N° Commande Fourn.')->searchable()->sortable()->placeholder('N/A'),
                TextColumn::make('supplier.company_name')->label('Fournisseur')->searchable()->sortable(),
                TextColumn::make('receipt_date')->label('Date Réception')->date()->sortable(),
                BadgeColumn::make('status')
                    ->label('Statut')
                    ->colors([
                        'warning' => 'pending_quality_check',
                        'success' => 'completed',
                        'danger' => 'cancelled',
                    ])
                    ->formatStateUsing(fn (string $state): string => self::$statuses[$state] ?? ucfirst($state)),
            ])
            ->filters([
                // Filtres
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
        return [
            'index' => Pages\ManageGoodsReceipts::route('/'),
        ];
    }
}
