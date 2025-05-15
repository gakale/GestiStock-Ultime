<?php
namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\SupplierResource\Pages;
// use App\Filament\Company\Resources\SupplierResource\RelationManagers;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

// Form Components
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Tabs;

// Table Columns
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck'; // Icône différente

    protected static ?string $navigationGroup = 'Tiers';

    protected static ?int $navigationSort = 2; // Après Clients

    protected static ?string $recordTitleAttribute = 'company_name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Supplier Details')
                    ->tabs([
                        Tabs\Tab::make('Informations Générales')
                            ->schema([
                                TextInput::make('company_name')
                                    ->label('Nom de l\'entreprise')
                                    ->required()
                                    ->maxLength(255),
                                Grid::make(2)->schema([
                                    TextInput::make('contact_first_name')
                                        ->label('Prénom du contact')
                                        ->maxLength(255),
                                    TextInput::make('contact_last_name')
                                        ->label('Nom du contact')
                                        ->maxLength(255),
                                ]),
                                TextInput::make('email')
                                    ->email()
                                    ->label('Adresse e-mail')
                                    ->maxLength(255)
                                    ->unique(Supplier::class, 'email', ignoreRecord: true),
                                TextInput::make('phone_number')
                                    ->label('Numéro de téléphone')
                                    ->tel()
                                    ->maxLength(255),
                                TextInput::make('vat_number')
                                    ->label('Numéro de TVA')
                                    ->maxLength(255),
                            ]),
                        Tabs\Tab::make('Adresse & Conditions')
                            ->schema([
                                TextInput::make('address_line1')->label('Adresse Ligne 1'),
                                TextInput::make('address_line2')->label('Adresse Ligne 2'),
                                Grid::make(3)->schema([
                                    TextInput::make('postal_code')->label('Code Postal'),
                                    TextInput::make('city')->label('Ville'),
                                    TextInput::make('country')->label('Pays'),
                                ]),
                                TextInput::make('state_province')->label('État / Province'),
                                TextInput::make('payment_terms')
                                    ->label('Conditions de paiement')
                                    ->maxLength(255)
                                    ->helperText('Ex: Net 30 jours, Comptant, etc.'),
                            ]),
                        Tabs\Tab::make('Autres Informations')
                            ->schema([
                                Textarea::make('notes')
                                    ->label('Notes internes')
                                    ->rows(4),
                                Toggle::make('is_active')
                                    ->label('Fournisseur Actif')
                                    ->default(true),
                            ])
                    ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company_name')
                    ->label('Nom Fournisseur')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('contact_first_name')
                    ->label('Prénom Contact')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('contact_last_name')
                    ->label('Nom Contact')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
            'view' => Pages\ViewSupplier::route('/{record}/view'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
