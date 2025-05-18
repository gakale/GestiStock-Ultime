<?php

namespace App\Filament\Company\Resources\SupplierInvoiceResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\SupplierPayment;
use App\Models\SupplierInvoice;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Closure;

class SupplierPaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';
    protected static ?string $recordTitleAttribute = 'payment_reference';
    protected static ?string $title = 'Paiements';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('supplier_id')
                    ->default(function (RelationManager $livewire): string {
                        /** @var SupplierInvoice $invoice */
                        $invoice = $livewire->getOwnerRecord();
                        return $invoice->supplier_id;
                    }),
                
                TextInput::make('payment_reference')
                    ->label('Référence de paiement')
                    ->required()
                    ->maxLength(255)
                    ->unique(SupplierPayment::class, 'payment_reference', ignoreRecord: true),
                
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
                    ->default(function (RelationManager $livewire): float {
                        /** @var SupplierInvoice $invoice */
                        $invoice = $livewire->getOwnerRecord();
                        // Par défaut, suggérer le solde dû de la facture comme montant total
                        return $invoice->total_amount - $invoice->amount_paid;
                    })
                    ->reactive()
                    ->afterStateUpdated(function (Get $get, Set $set) {
                        // Si le montant appliqué est déjà saisi et dépasse le nouveau montant total
                        $amountApplied = (float) $get('amount_applied');
                        $newAmount = (float) $get('amount');
                        
                        if ($amountApplied > $newAmount) {
                            // Ajuster le montant appliqué pour qu'il ne dépasse pas le montant total
                            $set('amount_applied', $newAmount);
                        }
                    }),
                
                TextInput::make('amount_applied')
                    ->label('Montant appliqué à cette facture')
                    ->helperText('Montant de ce paiement à appliquer à cette facture spécifique')
                    ->numeric()
                    ->prefix('€')
                    ->required()
                    ->default(function (RelationManager $livewire, Get $get): float {
                        /** @var SupplierInvoice $invoice */
                        $invoice = $livewire->getOwnerRecord();
                        $balanceDue = $invoice->total_amount - $invoice->amount_paid;
                        $paymentAmount = (float) $get('amount');
                        
                        // Si le montant du paiement n'est pas encore saisi, proposer le solde dû
                        if (!$paymentAmount) {
                            return $balanceDue;
                        }
                        
                        // Sinon, proposer le montant le plus petit entre le solde dû et le montant du paiement
                        return min($balanceDue, $paymentAmount);
                    })
                    ->rules([
                        function (Get $get) {
                            return function (string $attribute, $value, Closure $fail) use ($get) {
                                $paymentAmount = (float) $get('amount');
                                if ((float) $value > $paymentAmount) {
                                    $fail("Le montant appliqué ne peut pas dépasser le montant total du paiement.");
                                }
                            };
                        },
                    ]),
                
                Textarea::make('notes')
                    ->label('Notes')
                    ->maxLength(65535)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('payment_reference')
            ->columns([
                TextColumn::make('payment_reference')
                    ->label('Référence')
                    ->searchable(),
                
                TextColumn::make('payment_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                
                TextColumn::make('payment_method_name')
                    ->label('Méthode')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'bank_transfer' => 'Virement',
                        'cheque' => 'Chèque',
                        'cash' => 'Espèces',
                        'credit_card' => 'Carte',
                        'other' => 'Autre',
                        default => $state,
                    }),
                
                TextColumn::make('amount')
                    ->label('Montant total')
                    ->money('eur')
                    ->sortable(),
                
                TextColumn::make('pivot.amount_applied')
                    ->label('Montant appliqué')
                    ->money('eur')
                    ->sortable(),
                
                TextColumn::make('user.name')
                    ->label('Enregistré par')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->using(function (array $data, RelationManager $livewire): SupplierPayment {
                        /** @var SupplierInvoice $invoice */
                        $invoice = $livewire->getOwnerRecord();
                        $amountApplied = $data['amount_applied'];
                        
                        // Créer le paiement
                        $payment = SupplierPayment::create([
                            'supplier_id' => $invoice->supplier_id,
                            'user_id' => auth()->id(),
                            'payment_reference' => $data['payment_reference'],
                            'payment_date' => $data['payment_date'],
                            'payment_method_name' => $data['payment_method_name'],
                            'transaction_id' => $data['transaction_id'] ?? null,
                            'amount' => $data['amount'],
                            'notes' => $data['notes'] ?? null,
                        ]);
                        
                        // Attacher à la facture avec le montant appliqué
                        $payment->supplierInvoices()->attach($invoice->id, ['amount_applied' => $amountApplied]);
                        
                        // Mettre à jour le montant payé de la facture
                        $invoice->amount_paid += $amountApplied;
                        
                        // Mettre à jour le statut de la facture
                        if ($invoice->amount_paid >= $invoice->total_amount) {
                            $invoice->status = 'paid';
                        } elseif ($invoice->amount_paid > 0) {
                            $invoice->status = 'partially_paid';
                        }
                        
                        $invoice->save();
                        
                        return $payment;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->using(function (SupplierPayment $record, array $data, RelationManager $livewire): SupplierPayment {
                        /** @var SupplierInvoice $invoice */
                        $invoice = $livewire->getOwnerRecord();
                        
                        // Récupérer l'ancien montant appliqué
                        $oldAmountApplied = $record->supplierInvoices()->where('supplier_invoice_id', $invoice->id)->first()->pivot->amount_applied;
                        
                        // Calculer la différence
                        $amountDifference = $data['amount_applied'] - $oldAmountApplied;
                        
                        // Mettre à jour le paiement
                        $record->update([
                            'payment_reference' => $data['payment_reference'],
                            'payment_date' => $data['payment_date'],
                            'payment_method_name' => $data['payment_method_name'],
                            'transaction_id' => $data['transaction_id'] ?? null,
                            'amount' => $data['amount'],
                            'notes' => $data['notes'] ?? null,
                        ]);
                        
                        // Mettre à jour le montant appliqué dans la table pivot
                        $record->supplierInvoices()->updateExistingPivot($invoice->id, ['amount_applied' => $data['amount_applied']]);
                        
                        // Mettre à jour le montant payé de la facture
                        $invoice->amount_paid += $amountDifference;
                        
                        // Mettre à jour le statut de la facture
                        if ($invoice->amount_paid >= $invoice->total_amount) {
                            $invoice->status = 'paid';
                        } elseif ($invoice->amount_paid > 0) {
                            $invoice->status = 'partially_paid';
                        } else {
                            $invoice->status = 'pending';
                        }
                        
                        $invoice->save();
                        
                        return $record;
                    }),
                Tables\Actions\DeleteAction::make()
                    ->using(function (SupplierPayment $record, RelationManager $livewire): void {
                        /** @var SupplierInvoice $invoice */
                        $invoice = $livewire->getOwnerRecord();
                        
                        // Récupérer le montant appliqué
                        $amountApplied = $record->supplierInvoices()->where('supplier_invoice_id', $invoice->id)->first()->pivot->amount_applied;
                        
                        // Détacher la relation
                        $record->supplierInvoices()->detach($invoice->id);
                        
                        // Mettre à jour le montant payé de la facture
                        $invoice->amount_paid -= $amountApplied;
                        
                        // Mettre à jour le statut de la facture
                        if ($invoice->amount_paid <= 0) {
                            $invoice->status = 'pending';
                            $invoice->amount_paid = 0; // Pour éviter les valeurs négatives
                        } elseif ($invoice->amount_paid < $invoice->total_amount) {
                            $invoice->status = 'partially_paid';
                        }
                        
                        $invoice->save();
                        
                        // Si le paiement n'est plus lié à aucune facture, le supprimer
                        if ($record->supplierInvoices()->count() === 0) {
                            $record->delete();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
