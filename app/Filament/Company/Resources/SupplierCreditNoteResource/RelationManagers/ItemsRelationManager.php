<?php

namespace App\Filament\Company\Resources\SupplierCreditNoteResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Product;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Collection;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Support\RawJs;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    // protected static ?string $recordTitleAttribute = 'product_id'; // On va le rendre plus descriptif

    public function form(Form $form): Form // Ce formulaire est pour le modal Create/Edit du RM
    {
        return $form
            ->schema(self::getFormSchemaArray()); // Utilise le schéma partagé
    }

    // Méthode statique pour partager le schéma avec le Repeater
    public static function getFormSchemaArray(): array // Schéma utilisé aussi par le Repeater
    {
        return [
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
                            $set('unit_price', $product->purchase_price); // Ou 'price' si c'est le prix de vente
                            $set('description', $product->name); // ou $product->description
                            // $set('tax_rate', $product->tax_rate); // Si le produit a un taux de taxe par défaut
                        }
                    }
                })
                ->required()
                ->columnSpan(2), // Prend plus de place
            Textarea::make('description')
                ->label('Description')
                ->rows(2)
                ->columnSpan(2), // Prend plus de place
            TextInput::make('quantity')
                ->label('Quantité')
                ->numeric()
                ->default(1)
                ->required()
                ->reactive()
                ->minValue(0.01), // Ou 1 si quantités entières
            TextInput::make('unit_price')
                ->label('Prix Unitaire')
                ->numeric()
                ->required()
                ->reactive()
                ->prefix('€'), // Adaptez la devise
            TextInput::make('tax_rate')
                ->label('Taux TVA (%)')
                ->numeric()
                ->default(0) // Ou une valeur par défaut globale
                ->required()
                ->reactive()
                ->suffix('%'),
            // Les champs calculés (tax_amount, line_total) ne sont généralement pas dans le formulaire,
            // car ils sont calculés par le modèle. On peut les afficher en mode lecture seule si besoin.
            Forms\Components\Placeholder::make('line_total_placeholder')
                ->label('Total Ligne')
                ->content(function (Get $get) {
                    $qty = (float)($get('quantity') ?? 0);
                    $price = (float)($get('unit_price') ?? 0);
                    $taxRate = (float)($get('tax_rate') ?? 0);
                    $base = $qty * $price;
                    $tax = $base * ($taxRate / 100);
                    return number_format($base + $tax, 2, ',', ' ') . ' €';
                }),
        ];
    }

    public function table(Table $table): Table // Table affichée dans la page View et sous le Repeater
    {
        return $table
            ->recordTitle(fn ($record) => $record->product?->name ?? $record->description ?? 'Item')
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Produit')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->description),
                Tables\Columns\TextColumn::make('quantity')
                    ->alignRight()
                    ->numeric(),
                Tables\Columns\TextColumn::make('unit_price')
                    ->label('Prix Unit.')
                    ->money('eur') // Adaptez
                    ->alignRight(),
                Tables\Columns\TextColumn::make('tax_rate')
                    ->label('TVA (%)')
                    ->numeric()
                    ->alignRight()
                    ->suffix('%'),
                Tables\Columns\TextColumn::make('line_total')
                    ->label('Total Ligne')
                    ->money('eur') // Adaptez
                    ->alignRight(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        // Logique avant création via le modal du RM
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            // ->reorderable('sort_order_column') // Si vous avez une colonne pour l'ordre
            ->defaultSort('created_at', 'asc'); // Ou par un champ d'ordre
    }
}