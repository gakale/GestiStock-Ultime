<?php

namespace App\Filament\Company\Resources\SupplierInvoiceResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Product;
use App\Models\SupplierInvoice; // Propriétaire
use Filament\Forms\Get;
use Filament\Forms\Set;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';
    protected static ?string $recordTitleAttribute = 'description'; // Ou product.name

    // Schéma de formulaire partagé pour le modal du RM et le Repeater
    public function getFormSchema(): array
    {
        return [
            Forms\Components\Select::make('product_id')
                ->label('Produit (Optionnel)')
                ->relationship('product', 'name')
                ->searchable()
                ->preload()
                ->reactive()
                ->afterStateUpdated(function (Set $set, ?string $state) {
                    if ($state) {
                        $product = Product::find($state);
                        if ($product) {
                            $set('description', $product->name);
                            $set('unit_price', $product->purchase_price); // Prix d'achat du produit
                            // $set('tax_rate', $product->tax_rate_id ? $product->taxRate->rate : 0); // Si applicable
                        }
                    }
                }),
            Forms\Components\Textarea::make('description')
                ->label('Description Article/Service')
                ->required()
                ->rows(2)
                ->columnSpanFull(),
            Forms\Components\TextInput::make('quantity')->label('Quantité')->numeric()->required()->default(1)->reactive(),
            Forms\Components\TextInput::make('unit_price')->label('Prix Unitaire HT')->numeric()->required()->prefix('€')->reactive(),
            Forms\Components\TextInput::make('discount_percentage')->label('Remise (%)')->numeric()->default(0)->suffix('%')->reactive(),
            Forms\Components\TextInput::make('tax_rate')->label('TVA (%)')->numeric()->default(0)->suffix('%')->reactive(),
            Forms\Components\Placeholder::make('line_total_placeholder')
                ->label('Total Ligne TTC')
                ->content(function (Get $get): string {
                    $qty = (float)($get('quantity') ?? 0);
                    $price = (float)($get('unit_price') ?? 0);
                    $discount = (float)($get('discount_percentage') ?? 0);
                    $tax = (float)($get('tax_rate') ?? 0);

                    $base = $qty * $price;
                    $discountAmount = $base * ($discount / 100);
                    $afterDiscount = $base - $discountAmount;
                    $taxAmount = $afterDiscount * ($tax / 100);
                    $total = $afterDiscount + $taxAmount;
                    return number_format($total, 2, ',', ' ') . ' €';
                })->columnSpanFull(),
        ];
    }

    public function form(Form $form): Form
    {
        return $form->schema(self::getFormSchema());
    }

    public function table(Table $table): Table
    {
        return $table
            // ->recordTitleAttribute('description')
            ->columns([
                Tables\Columns\TextColumn::make('product.name')->label('Produit'),
                Tables\Columns\TextColumn::make('description')->limit(50),
                Tables\Columns\TextColumn::make('quantity')->numeric()->alignRight(),
                Tables\Columns\TextColumn::make('unit_price')->money('eur')->alignRight(),
                Tables\Columns\TextColumn::make('discount_percentage')->suffix('%')->alignRight(),
                Tables\Columns\TextColumn::make('tax_rate')->suffix('%')->alignRight(),
                Tables\Columns\TextColumn::make('line_total')->money('eur')->alignRight()->label('Total Ligne'),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function(array $data, RelationManager $livewire) {
                        /** @var SupplierInvoice $ownerRecord */
                        $ownerRecord = $livewire->getOwnerRecord();
                        // Permettre la création seulement si la facture est dans certains statuts
                        if (!in_array($ownerRecord->status, ['pending', 'draft'])) { // 'draft' si vous ajoutez ce statut
                            // Optionnel: lever une exception ou envoyer une notification
                            throw new \Exception("Impossible d'ajouter des articles à une facture fournisseur qui n'est pas en attente.");
                        }
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->visible(fn(RelationManager $livewire) => in_array($livewire->getOwnerRecord()->status, ['pending', 'draft'])),
                Tables\Actions\DeleteAction::make()->visible(fn(RelationManager $livewire) => in_array($livewire->getOwnerRecord()->status, ['pending', 'draft'])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->visible(fn(RelationManager $livewire) => in_array($livewire->getOwnerRecord()->status, ['pending', 'draft'])),
                ]),
            ]);
    }
}