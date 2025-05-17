<?php

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\ClientResource\Pages;
// use App\Filament\Company\Resources\ClientResource\RelationManagers;
use App\Models\Client;
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
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Tabs; // Pour organiser les champs
use Filament\Forms\Components\Placeholder;

// Table Columns
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Tiers';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'display_name'; // Utiliser notre accesseur

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Client Details')
                    ->tabs([
                        Tabs\Tab::make('Informations Principales')
                            ->schema([
                                Select::make('type')
                                    ->label('Type de client')
                                    ->options([
                                        'individual' => 'Particulier',
                                        'company' => 'Entreprise',
                                    ])
                                    ->required()
                                    ->default('individual')
                                    ->reactive() // Pour afficher/cacher des champs en fonction de la sélection
                                    ->afterStateUpdated(fn (callable $set) => $set('company_name', null)), // Réinitialiser si on change
                                TextInput::make('company_name')
                                    ->label('Nom de l\'entreprise')
                                    ->maxLength(255)
                                    ->visible(fn (Forms\Get $get) => $get('type') === 'company') // Afficher si type='company'
                                    ->required(fn (Forms\Get $get) => $get('type') === 'company'),
                                Grid::make(2)->schema([
                                    TextInput::make('first_name')
                                        ->label(fn (Forms\Get $get) => $get('type') === 'company' ? 'Prénom du contact' : 'Prénom')
                                        ->maxLength(255),
                                    TextInput::make('last_name')
                                        ->label(fn (Forms\Get $get) => $get('type') === 'company' ? 'Nom du contact' : 'Nom')
                                        ->required()
                                        ->maxLength(255),
                                ]),
                                TextInput::make('email')
                                    ->email()
                                    ->label('Adresse e-mail')
                                    ->maxLength(255)
                                    ->unique(Client::class, 'email', ignoreRecord: true),
                                TextInput::make('phone_number')
                                    ->label('Numéro de téléphone')
                                    ->tel()
                                    ->maxLength(255),
                                TextInput::make('vat_number')
                                    ->label('Numéro de TVA')
                                    ->maxLength(255),
                            ]),
                        Tabs\Tab::make('Adresse de Facturation')
                            ->schema([
                                TextInput::make('billing_address_line1')->label('Adresse Ligne 1'),
                                TextInput::make('billing_address_line2')->label('Adresse Ligne 2'),
                                Grid::make(3)->schema([
                                    TextInput::make('billing_postal_code')->label('Code Postal'),
                                    TextInput::make('billing_city')->label('Ville'),
                                    TextInput::make('billing_country')->label('Pays'), // Pourrait être un Select avec des pays
                                ]),
                                TextInput::make('billing_state_province')->label('État / Province'),
                            ]),
                        Tabs\Tab::make('Informations Financières')
                            ->schema([
                                Placeholder::make('balance_due_placeholder')
                                    ->label('Solde Dû Actuel')
                                    ->content(function (?Client $record): string {
                                        if (!$record || !$record->exists) {
                                            return 'N/A';
                                        }
                                        
                                        $balance = $record->balance_due;
                                        $status = $balance > 0 ? ' (Débiteur)' : ' (Aucune dette)';
                                        return number_format($balance, 2, ',', ' ') . ' €' . $status;
                                    })
                                    ->helperText('Montant total que ce client vous doit sur les factures non soldées.')
                                    ->columnSpanFull(),
                                
                                Placeholder::make('total_revenue_placeholder')
                                    ->label('Chiffre d\'Affaires Total avec ce Client')
                                    ->content(function (?Client $record): string {
                                        if (!$record || !$record->exists) {
                                            return 'N/A';
                                        }
                                        
                                        return number_format($record->total_revenue, 2, ',', ' ') . ' €';
                                    })
                                    ->columnSpanFull(),
                            ])
                            ->visible(fn (?Client $record) => $record && $record->exists),
                            
                        Tabs\Tab::make('Autres Informations')
                            ->schema([
                                Textarea::make('notes')
                                    ->label('Notes internes')
                                    ->rows(4),
                                Toggle::make('is_active')
                                    ->label('Client Actif')
                                    ->default(true),
                            ])
                    ])->columnSpanFull(), // Les Tabs prennent toute la largeur
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_name') // Utiliser l'accesseur
                    ->label('Nom / Entreprise')
                    ->searchable(['first_name', 'last_name', 'company_name']) // Champs de recherche
                    ->sortable(),
                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable(),
                TextColumn::make('phone_number')
                    ->label('Téléphone')
                    ->searchable(),
                // Nouvelle colonne pour le solde dû
                TextColumn::make('balance_due') // S'appuie sur l'accesseur balanceDue()
                    ->label('Solde Dû')
                    ->money('eur') // Adaptez la devise
                    ->alignRight()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success')
                    ->sortable(), // Le tri se fera sur la valeur calculée
                // Colonne pour le chiffre d'affaires total
                TextColumn::make('total_revenue') // S'appuie sur l'accesseur totalRevenue()
                    ->label('CA Total')
                    ->money('eur')
                    ->alignRight()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                Tables\Filters\SelectFilter::make('type')
                    ->label('Type de client')
                    ->options([
                        'individual' => 'Particulier',
                        'company' => 'Entreprise',
                    ]),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(), // Ajout pour voir les détails
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
            // RelationManagers pour adresses de livraison, commandes, etc.
        ];
    }


    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'edit' => Pages\EditClient::route('/{record}/edit'),
            'view' => Pages\ViewClient::route('/{record}/view'),
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
