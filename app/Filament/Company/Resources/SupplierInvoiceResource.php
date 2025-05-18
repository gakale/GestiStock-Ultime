<?php

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\SupplierInvoiceResource\Pages;
use App\Filament\Company\Resources\SupplierInvoiceResource\RelationManagers;
use App\Models\SupplierInvoice;
use App\Models\Supplier;
use App\Models\Product; // Pour le repeater/RM
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;

class SupplierInvoiceResource extends Resource
{
    protected static ?string $model = SupplierInvoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-down'; // Icône pour "facture entrante"

    protected static ?string $navigationGroup = 'Achats';

    protected static ?string $label = 'Facture Fournisseur';
    protected static ?string $pluralLabel = 'Factures Fournisseurs';
    protected static ?string $slug = 'supplier-invoices';

    protected static ?int $navigationSort = 3; // Après Commandes Fournisseurs, avant Avoirs Fournisseurs

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Informations Générales')
                    ->schema([
                        Select::make('supplier_id')
                            ->relationship('supplier', 'company_name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->label('Fournisseur'),
                        TextInput::make('supplier_invoice_number')
                            ->label('N° Facture Fournisseur')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(fn(string $operation) => $operation === 'create' ? 1 : 2),
                        DatePicker::make('invoice_date')
                            ->label('Date Facture')
                            ->required()
                            ->default(now()),
                        DatePicker::make('due_date')
                            ->label('Date d\'Échéance')
                            ->nullable(),
                        Select::make('status')
                            ->label('Statut')
                            ->options([
                                'pending' => 'En attente',
                                'partially_paid' => 'Partiellement Payée',
                                'paid' => 'Payée',
                                'overdue' => 'En Retard',
                                'cancelled' => 'Annulée',
                            ])
                            ->default('pending')
                            ->disabled() // Géré par la logique de paiement
                            ->dehydrated(), // Pour s'assurer qu'il est sauvegardé même si désactivé
                        FileUpload::make('attachment_path')
                            ->label('Pièce Jointe (Facture Originale)')
                            ->disk('public') // Ou votre disque configuré pour les PJ des tenants
                            ->directory('supplier_invoices_attachments')
                            ->visibility('private') // Ou 'public' selon votre configuration
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Articles de la Facture')
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->label(false) // Le label de la section suffit
                            ->schema((new RelationManagers\ItemsRelationManager())->getFormSchema()) // Réutiliser le schéma
                            ->columns(2)
                            ->addActionLabel('Ajouter un article')
                            ->defaultItems(1)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => Product::find($state['product_id'] ?? null)?->name ?? $state['description'] ?? 'Nouvel article')
                            ->deleteAction(fn (Forms\Components\Actions\Action $action) => $action->requiresConfirmation())
                            ->reorderable(false)
                            ->reactive()
                            ->afterStateUpdated(function (Get $get, Set $set, callable $livewire) {
                                self::updateTotals($get, $set, $livewire);
                            })
                            ->columnSpanFull(),
                    ]),

                Section::make('Totaux et Paiement')
                    ->schema([
                        TextInput::make('subtotal')->label('Sous-total')->numeric()->prefix('€')->readOnly()->default(0),
                        TextInput::make('taxes_amount')->label('Montant Taxes')->numeric()->prefix('€')->readOnly()->default(0),
                        TextInput::make('shipping_charges')->label('Frais de Port')->numeric()->prefix('€')->default(0)->reactive()
                            ->afterStateUpdated(fn (Get $get, Set $set, callable $livewire) => self::updateTotals($get, $set, $livewire)),
                        TextInput::make('total_amount')->label('Total Facture')->numeric()->prefix('€')->readOnly()->default(0)->columnSpan(1),
                        TextInput::make('amount_paid')->label('Montant Payé')->numeric()->prefix('€')->readOnly()->default(0)->columnSpan(1),
                         Forms\Components\Placeholder::make('balance_due')
                            ->label('Solde Dû')
                            ->content(function (Get $get): string {
                                $balance = (float)($get('total_amount') ?? 0) - (float)($get('amount_paid') ?? 0);
                                return number_format($balance, 2, ',', ' ') . ' €';
                            })->columnSpan(1),
                    ])->columns(3),

                Textarea::make('notes')
                    ->label('Notes Internes')
                    ->columnSpanFull(),
            ]);
    }

    // Fonction pour mettre à jour les totaux dans le formulaire
    public static function updateTotals(Get $get, Set $set, callable $livewire): void
    {
        $items = $get('items') ?? [];
        $subtotal = 0;
        $totalTaxes = 0;

        foreach ($items as $itemData) {
            if (empty($itemData['quantity']) || empty($itemData['unit_price'])) continue;

            $qty = (float)($itemData['quantity'] ?? 0);
            $price = (float)($itemData['unit_price'] ?? 0);
            $discountPercent = (float)($itemData['discount_percentage'] ?? 0);
            $taxRate = (float)($itemData['tax_rate'] ?? 0);

            $basePrice = $qty * $price;
            $discountAmount = $basePrice * ($discountPercent / 100);
            $priceAfterDiscount = $basePrice - $discountAmount;

            $subtotal += $priceAfterDiscount;
            $totalTaxes += $priceAfterDiscount * ($taxRate / 100);
        }

        $shipping = (float)($get('shipping_charges') ?? 0);
        $total = $subtotal + $totalTaxes + $shipping;

        $set('subtotal', round($subtotal, 2));
        $set('taxes_amount', round($totalTaxes, 2));
        $set('total_amount', round($total, 2));

        // Mettre à jour le composant Livewire pour refléter les changements (important pour les Placeholder)
        $livewire->js('setTimeout(() => { $wire.dispatchFormEvent(" নিলাম保存", null, null) }, 0)');
    }


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('supplier_invoice_number')->label('N° Fact. Fourn.')->searchable()->sortable(),
                TextColumn::make('supplier.company_name')->label('Fournisseur')->searchable()->sortable(),
                TextColumn::make('invoice_date')->label('Date Fact.')->date('d/m/Y')->sortable(),
                TextColumn::make('due_date')->label('Échéance')->date('d/m/Y')->sortable(),
                TextColumn::make('total_amount')->label('Total')->money('eur')->sortable(),
                TextColumn::make('amount_paid')->label('Payé')->money('eur')->sortable(),
                TextColumn::make('balance')->label('Solde Dû')->money('eur') // Utilise l'accesseur du modèle
                    ->color(fn ($state) => $state > 0 ? 'danger' : ($state == 0 ? 'success' : 'warning')), // warning si < 0 (trop payé)
                BadgeColumn::make('status')->label('Statut')->colors([
                    'gray' => 'pending',
                    'warning' => fn ($state) => $state === 'partially_paid' || $state === 'overdue',
                    'success' => 'paid',
                    'danger' => 'cancelled',
                ])->formatStateUsing(fn(string $state) => match($state){
                    'pending' => 'En attente',
                    'partially_paid' => 'Part. Payée',
                    'paid' => 'Payée',
                    'overdue' => 'En Retard',
                    'cancelled' => 'Annulée',
                    default => $state
                }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('supplier_id')->relationship('supplier', 'company_name')->label('Fournisseur'),
                Tables\Filters\SelectFilter::make('status')->options([
                    'pending' => 'En attente',
                    'partially_paid' => 'Partiellement Payée',
                    'paid' => 'Payée',
                    'overdue' => 'En Retard',
                    'cancelled' => 'Annulée',
                ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()->visible(fn(SupplierInvoice $record) => $record->status !== 'paid' && $record->status !== 'cancelled'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('invoice_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemsRelationManager::class,
            RelationManagers\SupplierPaymentsRelationManager::class, // Nous le créerons ensuite
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupplierInvoices::route('/'),
            'create' => Pages\CreateSupplierInvoice::route('/create'),
            'edit' => Pages\EditSupplierInvoice::route('/{record}/edit'),
            'view' => Pages\ViewSupplierInvoice::route('/{record}'),
        ];
    }
}