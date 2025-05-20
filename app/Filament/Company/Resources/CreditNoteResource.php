<?php

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\CreditNoteResource\Pages;
use App\Filament\Company\Resources\CreditNoteResource\RelationManagers;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\UnitOfMeasure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CreditNoteResource extends Resource
{
    protected static ?string $model = CreditNote::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-minus';
    protected static ?string $navigationGroup = 'Finance';
    protected static ?int $navigationSort = 4;
    protected static ?string $recordTitleAttribute = 'credit_note_number';
    
    public static array $statuses = [
        'draft' => 'Brouillon',
        'issued' => 'Émis',
        'applied' => 'Appliqué',
        'voided' => 'Annulé',
    ];
    
    public static array $reasonTypes = [
        'returned_goods' => 'Retour de marchandise',
        'invoice_error' => 'Erreur de facturation',
        'commercial_gesture' => 'Geste commercial',
        'other' => 'Autre',
    ];

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(3)->schema([
                    TextInput::make('credit_note_number')
                        ->label('Numéro d\'avoir')
                        ->default(fn() => CreditNote::generateNextCreditNoteNumber())
                        ->disabled()->dehydrated()->columnSpan(1),
                    
                    Select::make('client_id')
                        ->label('Client')
                        ->relationship('client', 'company_name', fn ($query) => $query->orderBy('company_name'))
                        ->getOptionLabelFromRecordUsing(fn(Client $record) => $record->getDisplayNameAttribute())
                        ->searchable()->preload()->required()
                        ->reactive() // Pour filtrer les factures
                        ->columnSpan(2),
                ]),
                
                Grid::make(3)->schema([
                    DatePicker::make('credit_note_date')
                        ->label('Date d\'avoir')
                        ->default(now())->required(),
                    
                    Select::make('reason')
                        ->label('Motif de l\'avoir')
                        ->options(self::$reasonTypes)
                        ->required(),
                    
                    Select::make('status')
                        ->label('Statut')
                        ->options(self::$statuses)
                        ->default('draft')
                        ->required(),
                ]),
                
                Grid::make(2)->schema([
                    Select::make('invoice_id')
                        ->label('Facture d\'origine (optionnel)')
                        ->options(function (Get $get): array {
                            $clientId = $get('client_id');
                            if ($clientId) {
                                return Invoice::where('client_id', $clientId)
                                    ->whereNotIn('status', ['draft', 'voided', 'cancelled'])
                                    ->pluck('invoice_number', 'id')
                                    ->toArray();
                            }
                            return [];
                        })
                        ->reactive()
                        ->afterStateUpdated(function ($state, Set $set) {
                            if ($state) {
                                // Récupérer les items de la facture pour pré-remplir
                                $invoice = Invoice::with('items.product')->find($state);
                                if ($invoice) {
                                    $items = $invoice->items->map(function ($item) {
                                        return [
                                            'product_id' => $item->product_id,
                                            'description' => $item->description,
                                            'quantity' => $item->quantity,
                                            'unit_price' => $item->unit_price,
                                            'discount_percentage' => $item->discount_percentage,
                                            'tax_rate' => $item->tax_rate,
                                            'line_total' => $item->line_total,
                                            'transaction_unit_id' => $item->transaction_unit_id,
                                        ];
                                    })->toArray();
                                    $set('items', $items);
                                }
                            }
                        }),
                    
                    Toggle::make('restock_items')
                        ->label('Retourner les articles en stock')
                        ->default(true)
                        ->helperText('Cochez pour augmenter le stock des produits retournés'),
                ]),
                
                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(3),
                
                Section::make('Détails de l\'avoir')
                    ->schema([
                        Repeater::make('items')
                            ->label('')
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        Select::make('product_id')
                                            ->label('Produit')
                                            ->options(function (): array {
                                                return Product::where('is_active', true)
                                                    ->orderBy('name')
                                                    ->pluck('name', 'id')
                                                    ->toArray();
                                            })
                                            ->searchable()
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, Set $set) {
                                                if ($state) {
                                                    $product = Product::find($state);
                                                    $set('unit_price', $product ? $product->selling_price : 0);
                                                    $set('description', $product ? $product->description : '');
                                                    
                                                    // Définir l'unité de transaction par défaut (unité de vente du produit)
                                                    if ($product && $product->sales_unit_id) {
                                                        $set('transaction_unit_id', $product->sales_unit_id);
                                                    }
                                                }
                                            })
                                            ->required()
                                            ->columnSpan(2),
                                            
                                        Select::make('transaction_unit_id')
                                            ->label('Unité')
                                            ->options(function (Get $get): array {
                                                $productId = $get('product_id');
                                                if (!$productId) {
                                                    return [];
                                                }
                                                
                                                // Récupérer toutes les unités compatibles avec ce produit
                                                $product = Product::find($productId);
                                                if (!$product) {
                                                    return [];
                                                }
                                                
                                                // Unités compatibles : unité de stock, unité de vente et leurs dérivées
                                                $unitIds = [];
                                                
                                                // Ajouter l'unité de vente si définie
                                                if ($product->sales_unit_id) {
                                                    $unitIds[] = $product->sales_unit_id;
                                                    // Ajouter les unités dérivées
                                                    $derivedUnits = UnitOfMeasure::where('base_unit_id', $product->sales_unit_id)
                                                        ->pluck('id')->toArray();
                                                    $unitIds = array_merge($unitIds, $derivedUnits);
                                                }
                                                
                                                // Ajouter l'unité de stock si différente
                                                if ($product->stock_unit_id && !in_array($product->stock_unit_id, $unitIds)) {
                                                    $unitIds[] = $product->stock_unit_id;
                                                    // Ajouter les unités dérivées
                                                    $derivedUnits = UnitOfMeasure::where('base_unit_id', $product->stock_unit_id)
                                                        ->pluck('id')->toArray();
                                                    $unitIds = array_merge($unitIds, $derivedUnits);
                                                }
                                                
                                                // Récupérer les unités puis transformer avec l'accesseur
                                                $units = UnitOfMeasure::whereIn('id', $unitIds)->get();
                                                return $units->pluck('name_with_symbol', 'id')->toArray();
                                            })
                                            ->reactive()
                                            ->required()
                                            ->columnSpan(1),
                                    ]),
                                
                                Textarea::make('description')
                                    ->label('Description')
                                    ->rows(2)
                                    ->columnSpanFull(),
                                
                                Grid::make(4)
                                    ->schema([
                                        TextInput::make('quantity')
                                            ->label('Quantité')
                                            ->numeric()
                                            ->default(1)
                                            ->minValue(0.01)
                                            ->step(0.01)
                                            ->required()
                                            ->reactive()
                                            ->afterStateUpdated(fn (Set $set, Get $get) => 
                                                $set('line_total', self::calculateLineTotal(
                                                    (float)($get('quantity') ?? 0), 
                                                    (float)($get('unit_price') ?? 0), 
                                                    (float)($get('discount_percentage') ?? 0), 
                                                    (float)($get('tax_rate') ?? 0)
                                                )))
                                            ->columnSpan(1),
                                        
                                        TextInput::make('unit_price')
                                            ->label('Prix unitaire')
                                            ->numeric()
                                            ->required()
                                            ->minValue(0)
                                            ->step(0.01)
                                            ->prefix(config('app.currency_symbol', '€'))
                                            ->reactive()
                                            ->afterStateUpdated(fn (Set $set, Get $get) => 
                                                $set('line_total', self::calculateLineTotal(
                                                    (float)($get('quantity') ?? 0), 
                                                    (float)($get('unit_price') ?? 0), 
                                                    (float)($get('discount_percentage') ?? 0), 
                                                    (float)($get('tax_rate') ?? 0)
                                                )))
                                            ->columnSpan(1),
                                        
                                        TextInput::make('discount_percentage')
                                            ->label('Remise %')
                                            ->numeric()
                                            ->default(0)
                                            ->required()
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->suffix('%')
                                            ->reactive()
                                            ->afterStateUpdated(fn (Set $set, Get $get) => 
                                                $set('line_total', self::calculateLineTotal(
                                                    (float)($get('quantity') ?? 0), 
                                                    (float)($get('unit_price') ?? 0), 
                                                    (float)($get('discount_percentage') ?? 0), 
                                                    (float)($get('tax_rate') ?? 0)
                                                )))
                                            ->columnSpan(1),
                                        
                                        TextInput::make('tax_rate')
                                            ->label('TVA %')
                                            ->numeric()
                                            ->default(20)
                                            ->required()
                                            ->minValue(0)
                                            ->suffix('%')
                                            ->reactive()
                                            ->afterStateUpdated(fn (Set $set, Get $get) => 
                                                $set('line_total', self::calculateLineTotal(
                                                    (float)($get('quantity') ?? 0), 
                                                    (float)($get('unit_price') ?? 0), 
                                                    (float)($get('discount_percentage') ?? 0), 
                                                    (float)($get('tax_rate') ?? 0)
                                                )))
                                            ->columnSpan(1),
                                    ]),
                                
                                TextInput::make('line_total')
                                    ->label('Total TTC')
                                    ->numeric()
                                    ->prefix(config('app.currency_symbol', '€'))
                                    ->disabled()
                                    ->dehydrated(),
                                    
                                Hidden::make('stock_unit_quantity')
                                    ->dehydrated(),
                            ])
                            ->columns(1)
                            ->defaultItems(1)
                            ->addActionLabel('Ajouter un article')
                            ->reorderable(false)
                            ->cloneable(false)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => 
                                $state['product_id'] 
                                    ? Product::find($state['product_id'])?->name . ' - ' . 
                                      number_format((float)($state['quantity'] ?? 0), 2) . ' x ' . 
                                      number_format((float)($state['unit_price'] ?? 0), 2) . '€'
                                    : null
                            ),
                    ]),
                
                Section::make('Totaux')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Placeholder::make('subtotal_placeholder')
                                    ->label('Total HT')
                                    ->content(fn (Get $get): string => 
                                        number_format(
                                            collect($get('items') ?? [])->sum(function ($item) {
                                                $quantity = (float)($item['quantity'] ?? 0);
                                                $unitPrice = (float)($item['unit_price'] ?? 0);
                                                $discountPercentage = (float)($item['discount_percentage'] ?? 0);
                                                
                                                $basePrice = $quantity * $unitPrice;
                                                $discountAmount = $basePrice * ($discountPercentage / 100);
                                                return $basePrice - $discountAmount;
                                            }),
                                            2
                                        ) . ' ' . config('app.currency_symbol', '€')
                                    ),
                                
                                Placeholder::make('tax_placeholder')
                                    ->label('Total TVA')
                                    ->content(fn (Get $get): string => 
                                        number_format(
                                            collect($get('items') ?? [])->sum(function ($item) {
                                                $quantity = (float)($item['quantity'] ?? 0);
                                                $unitPrice = (float)($item['unit_price'] ?? 0);
                                                $discountPercentage = (float)($item['discount_percentage'] ?? 0);
                                                $taxRate = (float)($item['tax_rate'] ?? 0);
                                                
                                                $basePrice = $quantity * $unitPrice;
                                                $discountAmount = $basePrice * ($discountPercentage / 100);
                                                $priceAfterDiscount = $basePrice - $discountAmount;
                                                return $priceAfterDiscount * ($taxRate / 100);
                                            }),
                                            2
                                        ) . ' ' . config('app.currency_symbol', '€')
                                    ),
                                
                                Placeholder::make('total_placeholder')
                                    ->label('Total TTC')
                                    ->content(fn (Get $get): string => 
                                        number_format(
                                            collect($get('items') ?? [])->sum(function ($item) {
                                                return (float)($item['line_total'] ?? 0);
                                            }),
                                            2
                                        ) . ' ' . config('app.currency_symbol', '€')
                                    ),
                            ]),
                    ])
                    ->visibleOn(['edit', 'view']),
            ])->columns(1);
    }
    
    // Méthode utilitaire pour calculer le total d'une ligne
    public static function calculateLineTotal(?float $quantity = 0, ?float $unitPrice = 0, ?float $discountPercentage = 0, ?float $taxRate = 0): float
    {
        // S'assurer que toutes les valeurs sont des nombres
        $quantity = $quantity ?? 0;
        $unitPrice = $unitPrice ?? 0;
        $discountPercentage = $discountPercentage ?? 0;
        $taxRate = $taxRate ?? 0;
        
        $basePrice = $quantity * $unitPrice;
        $discountAmount = $basePrice * ($discountPercentage / 100);
        $priceAfterDiscount = $basePrice - $discountAmount;
        $taxAmount = $priceAfterDiscount * ($taxRate / 100);
        return round($priceAfterDiscount + $taxAmount, 2);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Tri par défaut par date décroissante (plus récentes en premier)
            ->defaultSort('credit_note_date', 'desc')
            ->columns([
                TextColumn::make('credit_note_number')
                    ->label('Numéro d\'avoir')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('client.company_name')
                    ->label('Client')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('credit_note_date')
                    ->label('Date d\'avoir')
                    ->date()
                    ->sortable(),
                
                TextColumn::make('invoice.invoice_number')
                    ->label('Facture d\'origine')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('reason')
                    ->label('Motif')
                    ->formatStateUsing(fn (string $state): string => 
                        self::$reasonTypes[$state] ?? $state
                    )
                    ->sortable(),
                
                TextColumn::make('total_amount')
                    ->label('Montant TTC')
                    ->money(config('app.currency', 'eur'))
                    ->sortable(),
                
                TextColumn::make('status')
                    ->label('Statut')
                    ->formatStateUsing(fn (string $state): string => 
                        self::$statuses[$state] ?? $state
                    )
                    ->badge()
                    ->color(fn (string $state): string => 
                        match ($state) {
                            'draft' => 'gray',
                            'issued' => 'info',
                            'applied' => 'success',
                            'voided' => 'danger',
                            default => 'gray',
                        }
                    )
                    ->sortable(),
                
                IconColumn::make('restock_items')
                    ->label('Retour en stock')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(self::$statuses)
                    ->multiple(),
                
                SelectFilter::make('reason')
                    ->label('Motif')
                    ->options(self::$reasonTypes)
                    ->multiple(),
                
                Filter::make('credit_note_date')
                    ->form([
                        Forms\Components\DatePicker::make('date_from')
                            ->label('Date de début'),
                        Forms\Components\DatePicker::make('date_to')
                            ->label('Date de fin'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('credit_note_date', '>=', $date),
                            )
                            ->when(
                                $data['date_to'],
                                fn (Builder $query, $date): Builder => $query->whereDate('credit_note_date', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (CreditNote $record): bool => in_array($record->status, ['draft', 'issued'])),
                Tables\Actions\Action::make('issue')
                    ->label('Émettre')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (CreditNote $record): bool => $record->status === 'draft')
                    ->action(function (CreditNote $record): void {
                        $record->status = 'issued';
                        $record->save();
                    }),
                Tables\Actions\Action::make('void')
                    ->label('Annuler')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (CreditNote $record): bool => 
                        in_array($record->status, ['draft', 'issued'])
                    )
                    ->action(function (CreditNote $record): void {
                        $record->status = 'voided';
                        $record->save();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()->can('delete', CreditNote::class))
                        ->action(function (Collection $records): void {
                            $records->each(function ($record) {
                                if ($record->status === 'draft') {
                                    $record->delete();
                                }
                            });
                        }),
                ]),
            ]);
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
            'index' => Pages\ListCreditNotes::route('/'),
            'create' => Pages\CreateCreditNote::route('/create'),
            'edit' => Pages\EditCreditNote::route('/{record}/edit'),
            'view' => Pages\ViewCreditNote::route('/{record}'),
        ];
    }
}
