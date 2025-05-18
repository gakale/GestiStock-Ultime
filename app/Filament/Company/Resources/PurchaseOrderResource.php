<?php

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\PurchaseOrderResource\Pages;
use App\Filament\Company\Resources\PurchaseOrderResource\RelationManagers; // Nous l'utiliserons peut-être plus tard
use App\Models\PurchaseOrder;
use App\Models\Product; // Pour le selecteur de produits
use App\Models\Supplier; // Pour le selecteur de fournisseurs
use App\Models\TenantUser; // Pour l'utilisateur
use App\Models\UnitOfMeasure; // Pour les unités de mesure
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Get; // Pour la logique réactive
use Filament\Forms\Set; // Pour la logique réactive

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
use Filament\Tables\Columns\BadgeColumn; // Pour le statut

class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationGroup = 'Achats';

    protected static ?string $recordTitleAttribute = 'order_number';

    public static $statuses = [
        'draft' => 'Brouillon',
        'pending_approval' => 'En attente d\'approbation',
        'approved' => 'Approuvée',
        'ordered' => 'Commandée',
        'partially_received' => 'Partiellement Reçue',
        'fully_received' => 'Totalement Reçue',
        'cancelled' => 'Annulée',
    ];

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(3)->schema([
                    TextInput::make('order_number')
                        ->label('Numéro de Commande')
                        ->default(fn() => PurchaseOrder::generateNextOrderNumber()) // Fonction à créer dans le modèle ou ici
                        ->disabled() // Généré automatiquement
                        ->dehydrated() // S'assurer qu'il est sauvegardé
                        ->columnSpan(1),
                    Select::make('supplier_id')
                        ->label('Fournisseur')
                        ->relationship('supplier', 'company_name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->columnSpan(2),
                ]),
                Grid::make(3)->schema([
                    DatePicker::make('order_date')
                        ->label('Date de Commande')
                        ->default(now())
                        ->required(),
                    DatePicker::make('expected_delivery_date')
                        ->label('Date de Livraison Prévue'),
                    Select::make('status')
                        ->label('Statut')
                        ->options(self::$statuses)
                        ->default('draft')
                        ->required(),
                ]),

                Section::make('Lignes de la Commande')
                    ->schema([
                        Repeater::make('items')
                            ->label('Produits')
                            ->relationship() // Filament devrait détecter la relation 'items'
                            ->schema([
                                Select::make('product_id')
                                    ->label('Produit')
                                    ->options(Product::query()->pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->reactive()
                                    ->afterStateUpdated(function (Set $set, ?string $state) {
                                        if ($state) {
                                            $product = Product::find($state);
                                            if ($product) {
                                                // Pré-remplir les champs avec les données du produit
                                                $set('product_name', $product->name);
                                                $set('product_sku', $product->sku);
                                                $set('unit_price', $product->purchase_price ?? $product->selling_price ?? 0);
                                                
                                                // Pré-remplir l'unité de transaction avec l'unité d'achat par défaut du produit
                                                $set('transaction_unit_id', $product->purchase_unit_id ?? $product->stock_unit_id);
                                            }
                                        } else {
                                            // Réinitialiser les champs si aucun produit n'est sélectionné
                                            $set('product_name', null);
                                            $set('product_sku', null);
                                            $set('unit_price', null);
                                            $set('transaction_unit_id', null);
                                        }
                                    })
                                    ->required()
                                    ->columnSpan(3),
                                Hidden::make('product_name')->dehydrated(), // Champs cachés pour stocker les copies
                                Hidden::make('product_sku')->dehydrated(),
                                
                                Select::make('transaction_unit_id')
                                    ->label('Unité de Commande')
                                    ->options(function (Get $get) {
                                        $productId = $get('product_id');
                                        if (!$productId) {
                                            return [];
                                        }
                                        
                                        $product = Product::find($productId);
                                        if (!$product) {
                                            return [];
                                        }
                                        
                                        // Récupérer toutes les unités compatibles avec ce produit
                                        $unitIds = [];
                                        
                                        // Ajouter l'unité d'achat si définie
                                        if ($product->purchase_unit_id) {
                                            $unitIds[] = $product->purchase_unit_id;
                                            
                                            // Ajouter les unités dérivées de l'unité d'achat
                                            $derivedUnits = UnitOfMeasure::where('base_unit_id', $product->purchase_unit_id)->pluck('id')->toArray();
                                            $unitIds = array_merge($unitIds, $derivedUnits);
                                        }
                                        
                                        // Ajouter l'unité de stock si différente de l'unité d'achat
                                        if ($product->stock_unit_id && !in_array($product->stock_unit_id, $unitIds)) {
                                            $unitIds[] = $product->stock_unit_id;
                                            
                                            // Ajouter les unités dérivées de l'unité de stock
                                            $derivedUnits = UnitOfMeasure::where('base_unit_id', $product->stock_unit_id)->pluck('id')->toArray();
                                            $unitIds = array_merge($unitIds, $derivedUnits);
                                        }
                                        
                                        return UnitOfMeasure::whereIn('id', $unitIds)
                                            ->get()
                                            ->pluck('name_with_symbol', 'id')
                                            ->toArray();
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->reactive(),
                                TextInput::make('quantity')
                                    ->label('Quantité')
                                    ->integer()
                                    ->required()
                                    ->default(1)
                                    ->minValue(1)
                                    ->reactive()
                                    ->columnSpan(1),
                                TextInput::make('unit_price')
                                    ->label('Prix Unitaire')
                                    ->numeric()
                                    ->prefix('€') // Ou votre devise
                                    ->required()
                                    ->reactive()
                                    ->columnSpan(2),
                                // Placeholder pour le total de la ligne (non modifiable, calculé)
                                // Le calcul se fait dans le modèle PurchaseOrderItem
                                // On pourrait l'afficher ici avec ->disabled() et le calculer en JS aussi pour UX
                                // TextInput::make('line_total_display')
                                //     ->label('Total Ligne')
                                //     ->disabled()
                                //     ->numeric()
                                //     ->prefix('€'),
                                // Pour les remises et taxes par ligne (simplifié pour l'instant)
                                TextInput::make('discount_percentage')
                                    ->label('Remise (%)')
                                    ->numeric()->default(0)->minValue(0)->maxValue(100)
                                    ->reactive()->columnSpan(1),
                                TextInput::make('tax_rate')
                                    ->label('TVA (%)')
                                    ->numeric()->default(0)->minValue(0)->maxValue(100) // A adapter selon les taux de TVA
                                    ->reactive()->columnSpan(1),

                            ])
                            ->addActionLabel('Ajouter un produit')
                            ->columns(6) // Nombre de colonnes dans le repeater
                            ->defaultItems(1) // Commencer avec une ligne vide
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->deleteAction(
                                fn (Forms\Components\Actions\Action $action) => $action->requiresConfirmation(),
                            )
                            ->live() // Pour que les totaux globaux se mettent à jour
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                // Recalculer les totaux globaux ici si nécessaire (ou se fier au modèle)
                                // self::updateTotals($get, $set);
                            }),
                    ]),
                Section::make('Totaux et Informations Complémentaires')
                    ->schema([
                        Grid::make(2)->schema([
                            Placeholder::make('subtotal_display')
                                ->label('Sous-total')
                                ->content(function (Get $get): string {
                                    // Calcul basé sur les items (simplifié, le modèle fait le vrai calcul)
                                    $total = 0;
                                    $items = $get('items') ?? [];
                                    foreach ($items as $itemData) {
                                        $total += ($itemData['quantity'] ?? 0) * ($itemData['unit_price'] ?? 0);
                                    }
                                    return number_format($total, 2) . ' €';
                                }),
                            // On pourrait ajouter les champs pour discount_amount global, shipping_cost, taxes globales
                            // et un Placeholder pour le total_amount
                            TextInput::make('shipping_cost')
                                ->label('Frais de port')
                                ->numeric()->prefix('€')->default(0)->reactive(),
                            TextInput::make('discount_amount')
                                ->label('Remise globale')
                                ->numeric()->prefix('€')->default(0)->reactive(),
                            // Placeholder pour le total final
                            Placeholder::make('total_amount_display')
                                ->label('Total Commande')
                                ->content(function (Get $get): string {
                                    $subtotal = 0;
                                    $items = $get('items') ?? [];
                                    foreach ($items as $itemData) {
                                        // Ce calcul est approximatif pour l'affichage, le modèle fait le calcul final
                                        $base = (float)($itemData['quantity'] ?? 0) * (float)($itemData['unit_price'] ?? 0);
                                        $discount = $base * ((float)($itemData['discount_percentage'] ?? 0) / 100);
                                        $tax = ($base - $discount) * ((float)($itemData['tax_rate'] ?? 0) / 100);
                                        $subtotal += ($base - $discount + $tax);
                                    }
                                    $shipping = (float)($get('shipping_cost') ?? 0);
                                    $globalDiscount = (float)($get('discount_amount') ?? 0);
                                    return number_format($subtotal - $globalDiscount + $shipping, 2) . ' €';
                                }),
                        ]),
                         Grid::make(1)->schema([
                            Textarea::make('supplier_reference')->label('Référence Fournisseur'),
                            Textarea::make('notes_to_supplier')->label('Notes pour le Fournisseur'),
                            Textarea::make('internal_notes')->label('Notes Internes'),
                        ])
                    ])

            ])->columns(1); // Le formulaire principal est sur 1 colonne
    }

    // Méthode utilitaire pour mettre à jour les totaux dans le formulaire (pour UX)
    // La logique finale de calcul est dans les modèles PurchaseOrder et PurchaseOrderItem
    // public static function updateTotals(Get $get, Set $set): void
    // {
    //     $items = $get('items') ?? [];
    //     $subtotal = 0;
    //     foreach($items as $item) {
    //         $subtotal += ($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0);
    //     }
    //     $set('subtotal', $subtotal); // Mettre à jour un champ 'subtotal' caché si besoin

    //     $shipping = $get('shipping_cost') ?? 0;
    //     $discount = $get('discount_amount') ?? 0;
    //     // Calculer les taxes ici si elles sont globales
    //     // $taxes = ...
    //     // $set('total_amount', $subtotal + $taxes - $discount + $shipping);
    // }


    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('order_date', 'desc') // Tri par défaut décroissant sur la date de commande
            ->columns([
                TextColumn::make('order_number')->label('N° Commande')->searchable()->sortable(),
                TextColumn::make('supplier.company_name')->label('Fournisseur')->searchable()->sortable(),
                TextColumn::make('order_date')->label('Date')->date()->sortable(),
                TextColumn::make('items_count')
                    ->label('Articles')
                    ->getStateUsing(function ($record) {
                        return $record->items()->count() . ' article(s)';
                    })
                    ->sortable(),
                TextColumn::make('total_amount')->label('Total')->money('eur')->sortable(),
                BadgeColumn::make('status')
                    ->label('Statut')
                    ->colors([
                        'gray' => 'draft',
                        'warning' => 'pending_approval',
                        'primary' => 'approved',
                        'info' => 'ordered',
                        'success' => fn ($state) => in_array($state, ['partially_received', 'fully_received']),
                        'danger' => 'cancelled',
                    ])
                    ->formatStateUsing(fn (string $state): string => self::$statuses[$state] ?? ucfirst($state)),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label('Fournisseur')
                    ->relationship('supplier', 'company_name')
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
            RelationManagers\ItemsRelationManager::class, // Activé pour afficher les items dans une table séparée
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseOrders::route('/'),
            'create' => Pages\CreatePurchaseOrder::route('/create'),
            'edit' => Pages\EditPurchaseOrder::route('/{record}/edit'),
           'view' => Pages\ViewPurchaseOrder::route('/{record}/view'),
        ];
    }
}