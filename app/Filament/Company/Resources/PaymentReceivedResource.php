<?php

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\PaymentReceivedResource\Pages;
use App\Models\PaymentReceived;
use App\Models\Client;
use App\Models\Invoice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Get;

// Form Components
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;

// Table Columns
use Filament\Tables\Columns\TextColumn;

class PaymentReceivedResource extends Resource
{
    protected static ?string $model = PaymentReceived::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Finance';
    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'payment_reference';

    public static array $paymentMethods = [
        'bank_transfer' => 'Virement Bancaire',
        'check' => 'Chèque',
        'credit_card' => 'Carte de Crédit',
        'cash' => 'Espèces',
        'other' => 'Autre',
    ];

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(3)->schema([
                    TextInput::make('payment_reference')
                        ->label('Référence Paiement')
                        ->default(fn() => PaymentReceived::generateNextPaymentReference())
                        ->disabled()->dehydrated()->columnSpan(1),
                    Select::make('client_id')
                        ->label('Client')
                        ->relationship('client', 'company_name', fn ($query) => $query->orderBy('company_name'))
                        ->getOptionLabelFromRecordUsing(fn(Client $record) => $record->getDisplayNameAttribute())
                        ->searchable()->preload()->required()
                        ->reactive() // Pour filtrer les factures
                        ->columnSpan(2),
                ]),
                Grid::make(2)->schema([
                    DatePicker::make('payment_date')
                        ->label('Date de Paiement')
                        ->default(now())->required(),
                    TextInput::make('amount')
                        ->label('Montant Reçu')
                        ->numeric()->prefix(config('app.currency_symbol', '€'))
                        ->required()->minValue(0.01),
                ]),
                Grid::make(2)->schema([
                    Select::make('payment_method')
                        ->label('Méthode de Paiement')
                        ->options(self::$paymentMethods)
                        ->required(),
                    Select::make('invoice_id')
                        ->label('Facture Associée (Optionnel)')
                        ->options(function (Get $get): array {
                            $clientId = $get('client_id');
                            if ($clientId) {
                                // Afficher les factures non totalement payées pour ce client
                                return Invoice::where('client_id', $clientId)
                                    ->where(function ($query) { // Factures non payées ou partiellement payées
                                        $query->whereColumn('amount_paid', '<', 'total_amount')
                                              ->orWhereNull('amount_paid');
                                    })
                                    ->whereNotIn('status', ['draft', 'voided', 'cancelled'])
                                    ->orderBy('invoice_date', 'desc')
                                    ->limit(50)
                                    ->pluck('invoice_number', 'id')
                                    ->all();
                            }
                            return [];
                        })
                        ->searchable()
                        ->helperText('Laissez vide si c\'est un acompte ou un paiement global non affecté.'),
                ]),
                TextInput::make('transaction_id')->label('ID Transaction (si applicable)'),
                Textarea::make('notes')->label('Notes')->rows(3),
            ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('payment_reference')->label('Réf. Paiement')->searchable()->sortable(),
                TextColumn::make('client_id')
                    ->label('Client')
                    ->formatStateUsing(fn ($state, PaymentReceived $record) => $record->client ? $record->client->getDisplayNameAttribute() : '-')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('client', function (Builder $query) use ($search) {
                            $query->where('company_name', 'like', "%{$search}%")
                                  ->orWhere('first_name', 'like', "%{$search}%")
                                  ->orWhere('last_name', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(),
                TextColumn::make('invoice.invoice_number')->label('Facture Associée')->searchable()->sortable()->placeholder('N/A'),
                TextColumn::make('payment_date')->label('Date Paiement')->date()->sortable(),
                TextColumn::make('amount')->label('Montant')->money(config('app.currency', 'eur'))->sortable(),
                TextColumn::make('payment_method')
                    ->label('Méthode')
                    ->formatStateUsing(fn (string $state): string => self::$paymentMethods[$state] ?? ucfirst($state))
                    ->sortable(),
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

    // getRelations, getPages, getEloquentQuery similaires
    public static function getRelations(): array { return []; }
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentReceiveds::route('/'),
            'create' => Pages\CreatePaymentReceived::route('/create'),
            'edit' => Pages\EditPaymentReceived::route('/{record}/edit'),
            'view' => Pages\ViewPaymentReceived::route('/{record}'),
        ];
    }
}