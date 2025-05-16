<?php

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\SupplierCreditNoteResource\Pages;
use App\Filament\Company\Resources\SupplierCreditNoteResource\RelationManagers;
use App\Models\SupplierCreditNote;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\GoodsReceipt;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Collection;
use Filament\Support\RawJs;

class SupplierCreditNoteResource extends Resource
{
    protected static ?string $model = SupplierCreditNote::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-refund'; // Icône suggérée

    protected static ?string $navigationGroup = 'Achats'; // Ou 'Ventes' si vous gérez les retours clients ici

    protected static ?string $recordTitleAttribute = 'credit_note_number';

    protected static ?int $navigationSort = 4; // Pour ordonner dans le groupe Achats

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Wizard::make([
                    Wizard\Step::make('Informations Générales')
                        ->schema([
                            Forms\Components\TextInput::make('credit_note_number')
                                ->label('Numéro d\'avoir')
                                ->default(fn () => SupplierCreditNote::generateNextCreditNoteNumber())
                                ->disabled()
                                ->dehydrated()
                                ->required()
                                ->maxLength(255)
                                ->unique(SupplierCreditNote::class, 'credit_note_number', ignoreRecord: true),
                            Forms\Components\Select::make('supplier_id')
                                ->label('Fournisseur')
                                ->relationship('supplier', 'company_name') // Assurez-vous que 'company_name' est pertinent ou utilisez getDisplayNameAttribute
                                ->searchable()
                                ->preload()
                                ->required(),
                            Forms\Components\DatePicker::make('credit_note_date')
                                ->label('Date de l\'avoir')
                                ->default(now())
                                ->required(),
                            Forms\Components\Select::make('status')
                                ->label('Statut')
                                ->options([
                                    'draft' => 'Brouillon',
                                    'confirmed' => 'Confirmé',
                                    // 'paid' => 'Remboursé', // Si vous gérez les remboursements
                                    'cancelled' => 'Annulé',
                                ])
                                ->default('draft')
                                ->required(),
                            Forms\Components\Textarea::make('reason')
                                ->label('Raison du retour/avoir')
                                ->columnSpanFull(),
                        ])->columns(2),

                    Wizard\Step::make('Articles')
                        ->schema([
                            Forms\Components\Repeater::make('items')
                                ->label('Articles de l\'avoir')
                                ->relationship() // Indique que ce repeater gère la relation 'items'
                                ->schema(RelationManagers\ItemsRelationManager::getFormSchemaArray()) // Réutilise le schéma du RM
                                ->columns(2) // Nombre de colonnes pour chaque item dans le repeater
                                ->addActionLabel('Ajouter un article')
                                ->defaultItems(1) // Commence avec un item par défaut
                                ->collapsible()
                                ->itemLabel(fn (array $state): ?string => Product::find($state['product_id'] ?? null)?->name ?? 'Nouvel article')
                                ->deleteAction(
                                    fn (Forms\Components\Actions\Action $action) => $action->requiresConfirmation(),
                                )
                                ->reorderable(false) // Le réordonnancement n'a pas beaucoup de sens ici
                                ->reactive() // Pour que les totaux se mettent à jour
                                ->afterStateUpdated(function (Get $get, Set $set) { // Mettre à jour les totaux en direct
                                    $items = $get('items');
                                    $subtotal = self::calculateTotalsFromItems($items);
                                    $taxes = self::calculateTaxesFromItems($items);
                                    $total = self::calculateGrandTotalFromItems($items);
                                    
                                    // Mettre à jour les champs cachés pour la sauvegarde
                                    $set('subtotal', $subtotal);
                                    $set('taxes_amount', $taxes);
                                    $set('total_amount', $total);
                                })
                                ->columnSpanFull(),
                        ]),

                    Wizard\Step::make('Informations Complémentaires')
                        ->schema([
                            Forms\Components\Toggle::make('items_returned_to_supplier_stock')
                                ->label('Les articles ont quitté notre stock pour le fournisseur')
                                ->helperText('Cochez si une sortie de stock doit être enregistrée.')
                                ->default(true), // Par défaut, un avoir fournisseur implique un retour physique
                            Forms\Components\Select::make('purchase_order_id')
                                ->label('Commande Fournisseur d\'origine (Optionnel)')
                                ->relationship('purchaseOrder', 'order_number')
                                ->searchable()
                                ->preload(),
                            Forms\Components\Select::make('goods_receipt_id')
                                ->label('Bon de Réception d\'origine (Optionnel)')
                                ->relationship('goodsReceipt', 'receipt_number')
                                ->searchable()
                                ->preload(),
                            Forms\Components\Textarea::make('internal_notes')
                                ->label('Notes Internes')
                                ->columnSpanFull(),
                        ])->columns(2),

                    Wizard\Step::make('Totaux')
                        ->schema([
                            Forms\Components\Placeholder::make('subtotal_placeholder')
                                ->label('Sous-total')
                                ->content(function (Get $get) {
                                    // Calcul direct à partir des items
                                    $items = $get('items');
                                    $subtotal = self::calculateTotalsFromItems($items);
                                    // Mettre à jour le champ caché
                                    return number_format($subtotal, 2, ',', ' ') . ' €';
                                }),
                            Forms\Components\Placeholder::make('taxes_amount_placeholder')
                                ->label('Montant Taxes')
                                ->content(function (Get $get) {
                                    // Calcul direct à partir des items
                                    $items = $get('items');
                                    $taxes = self::calculateTaxesFromItems($items);
                                    return number_format($taxes, 2, ',', ' ') . ' €';
                                }),
                            Forms\Components\Placeholder::make('total_amount_placeholder')
                                ->label('Total Général')
                                ->content(function (Get $get) {
                                    // Calcul direct à partir des items
                                    $items = $get('items');
                                    $total = self::calculateGrandTotalFromItems($items);
                                    return number_format($total, 2, ',', ' ') . ' €';
                                }),
                            // Champs cachés pour stocker les totaux calculés
                            Forms\Components\Hidden::make('subtotal')
                                ->default(0)
                                ->dehydrated(true)
                                ->required(),
                            Forms\Components\Hidden::make('taxes_amount')
                                ->default(0)
                                ->dehydrated(true)
                                ->required(),
                            Forms\Components\Hidden::make('total_amount')
                                ->default(0)
                                ->dehydrated(true)
                                ->required(),
                        ]),
                ])->columnSpanFull()
                  ->contained(false), // Pour que le wizard prenne toute la largeur
            ]);
    }

    // Fonctions utilitaires pour calculer les totaux en direct dans le formulaire (avant la sauvegarde modèle)
    // Ces fonctions doivent être ajustées pour correspondre exactement à la logique de vos modèles
    protected static function calculateTotalsFromItems(?array $items): float
    {
        if (is_null($items)) return 0;
        return collect($items)->sum(function ($item) {
            return (float)($item['quantity'] ?? 0) * (float)($item['unit_price'] ?? 0);
        });
    }

    protected static function calculateTaxesFromItems(?array $items): float
    {
        if (is_null($items)) return 0;
        return collect($items)->sum(function ($item) {
            $base = (float)($item['quantity'] ?? 0) * (float)($item['unit_price'] ?? 0);
            return $base * ((float)($item['tax_rate'] ?? 0) / 100);
        });
    }

    protected static function calculateGrandTotalFromItems(?array $items): float
    {
        if (is_null($items)) return 0;
        $subtotal = self::calculateTotalsFromItems($items);
        $taxes = self::calculateTaxesFromItems($items);
        return $subtotal + $taxes;
    }


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('credit_note_number')
                    ->label('Numéro')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('supplier.company_name')
                    ->label('Fournisseur')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('credit_note_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('eur') // Adaptez la devise
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Brouillon',
                        'confirmed' => 'Confirmé',
                        'cancelled' => 'Annulé',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'warning',
                        'confirmed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('items_returned_to_supplier_stock')
                    ->label('Retour Stock')
                    ->boolean(),
                Tables\Columns\TextColumn::make('user.name') // Assurez-vous que TenantUser a 'name'
                    ->label('Créé par')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label('Fournisseur')
                    ->relationship('supplier', 'company_name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Brouillon',
                        'confirmed' => 'Confirmé',
                        'cancelled' => 'Annulé',
                    ])
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->after(function (SupplierCreditNote $record) {
                        // Logique de suppression de stock si l'avoir est "confirmed" et "items_returned_to_supplier_stock"
                        // Généralement, on ne supprime pas un avoir confirmé, on l'annule.
                        // La logique de stock est plutôt sur la confirmation/annulation.
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Fournisseurs';
    }
    
    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemsRelationManager::class,
        ];
    }
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupplierCreditNotes::route('/'),
            'create' => Pages\CreateSupplierCreditNote::route('/create'),
            'view' => Pages\ViewSupplierCreditNote::route('/{record}'),
            'edit' => Pages\EditSupplierCreditNote::route('/{record}/edit'),
        ];
    }
}
