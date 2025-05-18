<?php

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\SupplierPaymentResource\Pages;
use App\Models\SupplierPayment;
use App\Models\SupplierInvoice;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Repeater;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Carbon\Carbon;
use Illuminate\Support\HtmlString; // Add this import

class SupplierPaymentResource extends Resource
{
    protected static ?string $model = SupplierPayment::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    
    protected static ?string $navigationGroup = 'Achats';
    
    protected static ?string $label = 'Paiement Fournisseur';
    protected static ?string $pluralLabel = 'Paiements Fournisseurs';
    protected static ?string $slug = 'supplier-payments';
    
    protected static ?int $navigationSort = 4; // Après Factures Fournisseurs

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Informations de Paiement')
                    ->schema([
                        Select::make('supplier_id')
                            ->label('Fournisseur')
                            ->relationship('supplier', 'company_name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn (Set $set) => $set('invoice_applications', [])),
                        
                        TextInput::make('payment_reference')
                            ->label('Référence de paiement')
                            ->default(fn() => SupplierPayment::generateNextPaymentReference())
                            ->required()
                            ->maxLength(255),
                        
                        DatePicker::make('payment_date')
                            ->label('Date de paiement')
                            ->required()
                            ->default(now()),
                        
                        Select::make('payment_method_name')
                            ->label('Méthode de paiement')
                            ->options([
                                'bank_transfer' => 'Virement bancaire',
                                'cheque' => 'Chèque',
                                'cash' => 'Espèces',
                                'credit_card' => 'Carte de crédit',
                                'other' => 'Autre',
                            ])
                            ->required(),
                        
                        TextInput::make('transaction_id')
                            ->label('ID de transaction')
                            ->maxLength(255),
                        
                        TextInput::make('amount')
                            ->label('Montant total du paiement')
                            ->numeric()
                            ->prefix('€')
                            ->required()
                            ->reactive(),
                        
                        Textarea::make('notes')
                            ->label('Notes')
                            ->maxLength(65535)
                            ->columnSpanFull(),
                    ])->columns(2),
                
                Section::make('Application aux Factures')
                    ->schema([
                        Repeater::make('invoice_applications')
                            ->label('Appliquer aux factures')
                            ->schema([
                                Select::make('supplier_invoice_id')
                                    ->label('Facture')
                                    ->options(function (Get $get) {
                                        $supplierId = $get('../../supplier_id');
                                        if (!$supplierId) return [];
                                        
                                        return SupplierInvoice::where('supplier_id', $supplierId)
                                            ->where(function ($query) {
                                                $query->where('status', 'pending')
                                                    ->orWhere('status', 'partially_paid')
                                                    ->orWhere('status', 'overdue');
                                            })
                                            ->get()
                                            ->mapWithKeys(function ($invoice) {
                                                $balance = $invoice->total_amount - $invoice->amount_paid;
                                                return [
                                                    $invoice->id => "{$invoice->supplier_invoice_number} - {$invoice->invoice_date->format('d/m/Y')} - Solde: {$balance}€"
                                                ];
                                            });
                                    })
                                    ->searchable()
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                        $invoiceId = $get('supplier_invoice_id');
                                        if (!$invoiceId) {
                                            $set('amount_applied', 0);
                                            return;
                                        }
                                        
                                        $invoice = SupplierInvoice::find($invoiceId);
                                        if (!$invoice) {
                                            $set('amount_applied', 0);
                                            return;
                                        }
                                        
                                        // Calculer le solde de la facture
                                        $balance = $invoice->total_amount - $invoice->amount_paid;
                                        
                                        // Calculer le montant restant à allouer pour ce paiement
                                        $totalPaymentAmount = (float) $get('../../amount') ?: 0;
                                        $allocatedAmount = 0;
                                        
                                        // Parcourir toutes les applications de factures pour calculer le montant déjà alloué
                                        $applications = $get('../../invoice_applications') ?: [];
                                        foreach ($applications as $index => $application) {
                                            // Ignorer l'élément actuel pour éviter de le compter deux fois
                                            if ($index === $get('../key')) continue;
                                            $allocatedAmount += (float) ($application['amount_applied'] ?? 0);
                                        }
                                        
                                        $remainingToAllocateForThisPayment = $totalPaymentAmount - $allocatedAmount;
                                        
                                        // Proposer le montant le plus petit entre le solde de la facture et ce qu'il reste à allouer
                                        $set('amount_applied', min($balance, max(0, $remainingToAllocateForThisPayment)));
                                    }),
                                
                                TextInput::make('amount_applied')
                                    ->label('Montant appliqué')
                                    ->numeric()
                                    ->prefix('€')
                                    ->required()
                                    ->rules([
                                        function (Get $get) {
                                            return function (string $attribute, $value, \Closure $fail) use ($get) {
                                                // Vérifier que le montant appliqué ne dépasse pas le montant total du paiement
                                                $paymentAmount = (float) $get('../../amount');
                                                if ((float) $value > $paymentAmount) {
                                                    $fail("Le montant appliqué ne peut pas dépasser le montant total du paiement ({$paymentAmount}€).");
                                                }
                                                
                                                // Vérifier que le montant appliqué ne dépasse pas le solde de la facture
                                                $invoiceId = $get('supplier_invoice_id');
                                                if ($invoiceId) {
                                                    $invoice = SupplierInvoice::find($invoiceId);
                                                    if ($invoice) {
                                                        $balance = $invoice->total_amount - $invoice->amount_paid;
                                                        if ((float) $value > $balance) {
                                                            $fail("Le montant appliqué ne peut pas dépasser le solde de la facture ({$balance}€).");
                                                        }
                                                    }
                                                }
                                            };
                                        },
                                    ]),
                            ])
                            ->columns(2)
                            ->itemLabel(function (array $state): ?string {
                                $invoiceId = $state['supplier_invoice_id'] ?? null;
                                if (!$invoiceId) return 'Nouvelle application';
                                
                                $invoice = SupplierInvoice::find($invoiceId);
                                if (!$invoice) return 'Facture inconnue';
                                
                                $amount = $state['amount_applied'] ?? 0;
                                return "Facture {$invoice->supplier_invoice_number} - {$amount}€";
                            })
                            ->reorderable(false)
                            ->collapsible()
                            ->collapseAllAction(null)
                            ->addActionLabel('Ajouter une facture')
                            ->defaultItems(0),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('payment_reference')
                    ->label('Référence')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->icon('heroicon-o-document-text')
                    ->color('primary')
                    ->sortable(true, function(Builder $query, string $direction) {
                        return $query->orderBy('payment_reference', 'desc');
                    }),
                
                TextColumn::make('supplier.company_name')
                    ->label('Fournisseur')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-building-storefront')
                    ->color('gray')
                    ->sortable(true, function(Builder $query, string $direction) {
                        return $query->orderBy('supplier_id', 'desc');
                    }),
                
                TextColumn::make('payment_date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable()
                    ->icon('heroicon-o-calendar')
                    ->color('success')
                    ->sortable(true, function(Builder $query, string $direction) {
                        return $query->orderBy('payment_date', 'desc');
                    }),
                
                Tables\Columns\BadgeColumn::make('payment_method_name')
                    ->label('Méthode')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'bank_transfer' => 'Virement',
                        'cheque' => 'Chèque',
                        'cash' => 'Espèces',
                        'credit_card' => 'Carte',
                        'other' => 'Autre',
                        default => $state,
                    })
                    ->colors([
                        'primary' => 'bank_transfer',
                        'success' => 'cash',
                        'warning' => 'cheque',
                        'info' => 'credit_card',
                        'gray' => 'other',
                    ]),
                
                TextColumn::make('amount')
                    ->label('Montant')
                    ->money('eur')
                    ->sortable()
                    ->icon('heroicon-o-banknotes')
                    ->color(fn ($state) => $state > 1000 ? 'success' : 'gray')
                    ->sortable(true, function(Builder $query, string $direction) {
                        return $query->orderBy('amount', 'desc');
                    }),
                
                TextColumn::make('supplierInvoices.count')
                    ->label('Factures liées')
                    ->counts('supplierInvoices')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('info'),
                
                TextColumn::make('user.name')
                    ->label('Créé par')
                    ->toggleable(isToggledHiddenByDefault: true),
                
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('supplier_id')
                    ->relationship('supplier', 'company_name')
                    ->label('Fournisseur')
                    ->searchable()
                    ->preload(),
                
                Tables\Filters\SelectFilter::make('payment_method_name')
                    ->label('Méthode de paiement')
                    ->options([
                        'bank_transfer' => 'Virement bancaire',
                        'cheque' => 'Chèque',
                        'cash' => 'Espèces',
                        'credit_card' => 'Carte de crédit',
                        'other' => 'Autre',
                    ]),
                
                Tables\Filters\Filter::make('date')
                    ->form([
                        Forms\Components\DatePicker::make('payment_date_from')
                            ->label('Date de début'),
                        Forms\Components\DatePicker::make('payment_date_to')
                            ->label('Date de fin'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['payment_date_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('payment_date', '>=', $date),
                            )
                            ->when(
                                $data['payment_date_to'],
                                fn (Builder $query, $date): Builder => $query->whereDate('payment_date', '<=', $date),
                            );
                    })
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->color('info')
                    ->icon('heroicon-o-eye'),
                    
                Tables\Actions\EditAction::make()
                    ->color('warning')
                    ->icon('heroicon-o-pencil'),
                    
                    Tables\Actions\Action::make('voir_factures')
                    ->label('Factures liées')
                    ->color('primary')
                    ->icon('heroicon-o-document-text')
                    ->modalHeading('Factures liées à ce paiement')
                    ->modalDescription(fn (SupplierPayment $record) => "Paiement {$record->payment_reference} du {$record->payment_date->format('d/m/Y')} d'un montant de {$record->amount}€")
                    ->modalIcon('heroicon-o-document-text')
                    ->modalSubmitAction(false) // Pas de bouton de soumission
                    ->modalCancelActionLabel('Fermer')
                    ->modalContent(function (SupplierPayment $record) {
                        $invoices = $record->supplierInvoices;
                        
                        if ($invoices->isEmpty()) {
                            return new HtmlString('<div class="p-4 text-center text-gray-700 font-medium">Aucune facture n\'est liée à ce paiement.</div>');
                        }
                        
                        $html = '<div class="space-y-4 dark:text-white">';
                        
                        foreach ($invoices as $invoice) {
                            // Préparation des valeurs avant insertion dans le HTML
                            $viewUrl = route('filament.company.resources.supplier-invoices.view', ['record' => $invoice->id]);
                            $invoiceNumber = $invoice->supplier_invoice_number;
                            $invoiceDateFormatted = $invoice->invoice_date->format('d/m/Y');
                            $dueDateFormatted = $invoice->due_date ? $invoice->due_date->format('d/m/Y') : 'N/A';
                            $totalAmount = $invoice->total_amount;
                            $amountApplied = $invoice->pivot->amount_applied;
                            
                            // Préparation du badge de statut
                            $status = match($invoice->status) {
                                'pending' => '<span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">En attente</span>',
                                'partially_paid' => '<span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800">Part. Payée</span>',
                                'paid' => '<span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Payée</span>',
                                'overdue' => '<span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">En retard</span>',
                                'cancelled' => '<span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">Annulée</span>',
                                default => '<span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">' . $invoice->status . '</span>',
                            };
                            
                            // Construction du HTML pour cette facture
                            $html .= <<<HTML
                            <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow">
                                <div class="flex justify-between items-center mb-2">
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Facture {$invoiceNumber}</h3>
                                    {$status}
                                </div>
                                <div class="grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <p class="text-gray-700 dark:text-gray-300 font-semibold">Date facture</p>
                                        <p class="font-medium text-gray-900 dark:text-white">{$invoiceDateFormatted}</p>
                                    </div>
                                    <div>
                                        <p class="text-gray-700 dark:text-gray-300 font-semibold">Date échéance</p>
                                        <p class="font-medium text-gray-900 dark:text-white">{$dueDateFormatted}</p>
                                    </div>
                                    <div>
                                        <p class="text-gray-700 dark:text-gray-300 font-semibold">Montant total</p>
                                        <p class="font-medium text-gray-900 dark:text-white">{$totalAmount} €</p>
                                    </div>
                                    <div>
                                        <p class="text-gray-700 dark:text-gray-300 font-semibold">Montant appliqué</p>
                                        <p class="font-medium text-green-600 dark:text-green-400">{$amountApplied} €</p>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <a href="{$viewUrl}" target="_blank" class="inline-flex items-center px-4 py-2 text-sm font-bold text-white bg-indigo-600 dark:bg-indigo-500 border-2 border-indigo-700 dark:border-indigo-400 rounded-md hover:bg-indigo-700 dark:hover:bg-indigo-600 shadow-sm transition-colors duration-200 ring-2 ring-offset-2 ring-indigo-300 dark:ring-indigo-700">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                        Voir la facture
                                    </a>
                                </div>
                            </div>
                            HTML;
                        }
                        
                        $html .= '</div>';
                        
                        return new HtmlString($html);
                    }),
                    
                Tables\Actions\DeleteAction::make()
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->icon('heroicon-o-trash')
                        ->color('danger'),
                    
                    Tables\Actions\BulkAction::make('export')
                        ->label('Exporter')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            // Logique d'export à implémenter
                            // Pour l'instant, juste une notification
                            \Filament\Notifications\Notification::make()
                                ->title('Export en cours')
                                ->body(count($records) . ' paiements sélectionnés pour export')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->emptyStateHeading('Aucun paiement fournisseur')
            ->emptyStateDescription('Vous n\'avez pas encore enregistré de paiements fournisseurs.')
            ->emptyStateIcon('heroicon-o-banknotes')
            ->emptyStateActions([
                Tables\Actions\Action::make('create')
                    ->label('Créer un paiement')
                    ->url(route('filament.company.resources.supplier-payments.create'))
                    ->icon('heroicon-o-plus')
                    ->color('primary'),
            ])
            ->defaultSort('payment_date', 'desc');
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
            'index' => Pages\ListSupplierPayments::route('/'),
            'create' => Pages\CreateSupplierPayment::route('/create'),
            'edit' => Pages\EditSupplierPayment::route('/{record}/edit'),
            'view' => Pages\ViewSupplierPayment::route('/{record}'),
        ];
    }    
}