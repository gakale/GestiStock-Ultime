<?php

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\CreditNoteResource\Pages;
use App\Filament\Company\Resources\CreditNoteResource\RelationManagers;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
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
                                    ->orderBy('invoice_date', 'desc')
                                    ->limit(50)
                                    ->pluck('invoice_number', 'id')
                                    ->all();
                            }
                            return [];
                        })
                        ->searchable()
                        ->reactive()
                        ->afterStateUpdated(function (Set $set, $state) {
                            if (empty($state)) {
                                $set('invoice_items', []);
                            }
                        }),
                    
                    Toggle::make('restock_items')
                        ->label('Remettre les articles en stock')
                        ->helperText('Activer si les articles doivent être retournés en stock')
                        ->default(false),
                ]),
                
                Textarea::make('internal_notes')
                    ->label('Notes internes')
                    ->rows(2),
                
                Section::make('Articles de l\'avoir')
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Grid::make(6)->schema([
                                    Select::make('product_id')
                                        ->label('Produit')
                                        ->options(Product::all()->pluck('name', 'id'))
                                        ->searchable()
                                        ->reactive()
                                        ->afterStateUpdated(function (Set $set, $state) {
                                            if ($state) {
                                                $product = Product::find($state);
                                                if ($product) {
                                                    $set('product_name', $product->name);
                                                    $set('product_sku', $product->sku);
                                                    $set('unit_price', $product->selling_price);
                                                    $set('tax_rate', $product->tax_rate);
                                                }
                                            }
                                        })
                                        ->columnSpan(3),
                                    
                                    TextInput::make('product_name')
                                        ->label('Nom du produit')
                                        ->required()
                                        ->columnSpan(3),
                                ]),
                                
                                Grid::make(6)->schema([
                                    TextInput::make('product_sku')
                                        ->label('Référence')
                                        ->columnSpan(1),
                                    
                                    TextInput::make('quantity')
                                        ->label('Quantité')
                                        ->numeric()
                                        ->default(1)
                                        ->minValue(1)
                                        ->required()
                                        ->reactive()
                                        ->afterStateUpdated(fn (Set $set, Get $get) => 
                                            $set('line_total', self::calculateLineTotal(
                                                $get('quantity'), 
                                                $get('unit_price'), 
                                                $get('discount_percentage'), 
                                                $get('tax_rate')
                                            )))
                                        ->columnSpan(1),
                                    
                                    TextInput::make('unit_price')
                                        ->label('Prix unitaire')
                                        ->numeric()
                                        ->prefix(config('app.currency_symbol', '€'))
                                        ->required()
                                        ->reactive()
                                        ->afterStateUpdated(fn (Set $set, Get $get) => 
                                            $set('line_total', self::calculateLineTotal(
                                                $get('quantity'), 
                                                $get('unit_price'), 
                                                $get('discount_percentage'), 
                                                $get('tax_rate')
                                            )))
                                        ->columnSpan(1),
                                    
                                    TextInput::make('discount_percentage')
                                        ->label('Remise %')
                                        ->numeric()
                                        ->default(0)
                                        ->minValue(0)
                                        ->maxValue(100)
                                        ->suffix('%')
                                        ->reactive()
                                        ->afterStateUpdated(fn (Set $set, Get $get) => 
                                            $set('line_total', self::calculateLineTotal(
                                                $get('quantity'), 
                                                $get('unit_price'), 
                                                $get('discount_percentage'), 
                                                $get('tax_rate')
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
                                                $get('quantity'), 
                                                $get('unit_price'), 
                                                $get('discount_percentage'), 
                                                $get('tax_rate') ?? 0
                                            )))
                                        ->columnSpan(1),
                                    
                                    TextInput::make('line_total')
                                        ->label('Total')
                                        ->numeric()
                                        ->prefix(config('app.currency_symbol', '€'))
                                        ->disabled()
                                        ->dehydrated()
                                        ->columnSpan(1),
                                ]),
                                
                                Textarea::make('description')
                                    ->label('Description')
                                    ->rows(2),
                            ])
                            ->columns(1)
                            ->itemLabel(fn (array $state): ?string => 
                                $state['product_name'] ?? null
                            )
                            ->defaultItems(0)
                            ->reorderable(false)
                            ->addActionLabel('Ajouter un article')
                            ->required(),
                    ]),
                
                Section::make('Récapitulatif')
                    ->schema([
                        Grid::make(3)->schema([
                            Placeholder::make('subtotal_placeholder')
                                ->label('Sous-total HT')
                                ->content(function (Get $get, Set $set, ?CreditNote $record): string {
                                    if ($record) {
                                        return number_format($record->subtotal, 2) . ' ' . config('app.currency_symbol', '€');
                                    }
                                    return '0.00 ' . config('app.currency_symbol', '€');
                                }),
                            
                            Placeholder::make('taxes_amount_placeholder')
                                ->label('Total TVA')
                                ->content(function (?CreditNote $record): string {
                                    if ($record) {
                                        return number_format($record->taxes_amount, 2) . ' ' . config('app.currency_symbol', '€');
                                    }
                                    return '0.00 ' . config('app.currency_symbol', '€');
                                }),
                            
                            Placeholder::make('total_amount_placeholder')
                                ->label('Total TTC')
                                ->content(function (?CreditNote $record): string {
                                    if ($record) {
                                        return number_format($record->total_amount, 2) . ' ' . config('app.currency_symbol', '€');
                                    }
                                    return '0.00 ' . config('app.currency_symbol', '€');
                                }),
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
            ->columns([
                TextColumn::make('credit_note_number')
                    ->label('Numéro d\'avoir')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('client_id')
                    ->label('Client')
                    ->formatStateUsing(fn ($state, CreditNote $record) => 
                        $record->client ? $record->client->getDisplayNameAttribute() : '-'
                    )
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('client', function (Builder $query) use ($search) {
                            $query->where('company_name', 'like', "%{$search}%")
                                  ->orWhere('first_name', 'like', "%{$search}%")
                                  ->orWhere('last_name', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(),
                
                TextColumn::make('invoice.invoice_number')
                    ->label('Facture d\'origine')
                    ->searchable()
                    ->sortable()
                    ->placeholder('N/A'),
                
                TextColumn::make('credit_note_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                
                TextColumn::make('reason')
                    ->label('Motif')
                    ->formatStateUsing(fn (string $state): string => 
                        self::$reasonTypes[$state] ?? ucfirst($state)
                    )
                    ->sortable(),
                
                TextColumn::make('total_amount')
                    ->label('Montant')
                    ->money(config('app.currency', 'eur'))
                    ->sortable(),
                
                TextColumn::make('status')
                    ->label('Statut')
                    ->formatStateUsing(fn (string $state): string => 
                        self::$statuses[$state] ?? ucfirst($state)
                    )
                    ->badge()
                    ->color(fn (string $state): string => 
                        match ($state) {
                            'draft' => 'gray',
                            'issued' => 'primary',
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
                    ->visible(fn (CreditNote $record): bool => $record->status === 'draft'),
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
            // Pas de relations supplémentaires pour l'instant
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
