<?php

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\ActivityLogResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Builder;

class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;
    
    public static function makeFilamentTranslatableContentDriver(): ?\Filament\Support\Contracts\TranslatableContentDriver
    {
        return null;
    }

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 100;

    protected static ?string $recordTitleAttribute = 'description';

    public static function getModelLabel(): string
    {
        return __('Journal d\'activité');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Journal d\'activités');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Détails de l\'activité')
                    ->schema([
                        Forms\Components\TextInput::make('log_name')
                            ->label('Catégorie')
                            ->disabled(),
                        Forms\Components\TextInput::make('description')
                            ->label('Description')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('created_at')
                            ->label('Date et heure')
                            ->displayFormat('d/m/Y H:i:s')
                            ->disabled(),
                        Forms\Components\TextInput::make('causer.name')
                            ->label('Utilisateur')
                            ->disabled(),
                    ])->columns(2),
                
                Forms\Components\Section::make('Modifications')
                    ->schema([
                        Forms\Components\KeyValue::make('properties.attributes')
                            ->label('Nouvelles valeurs')
                            ->disabled(),
                        Forms\Components\KeyValue::make('properties.old')
                            ->label('Anciennes valeurs')
                            ->disabled(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('log_name')
                    ->label('Catégorie')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'client_activity' => 'Clients',
                        'product_activity' => 'Produits',
                        'supplier_activity' => 'Fournisseurs',
                        'invoice_activity' => 'Factures',
                        'invoice_item_activity' => 'Lignes de facture',
                        default => $state,
                    })
                    ->colors([
                        'primary' => 'default',
                        'success' => fn ($state): bool => $state === 'client_activity',
                        'warning' => fn ($state): bool => $state === 'product_activity',
                        'danger' => fn ($state): bool => $state === 'invoice_activity',
                        'info' => fn ($state): bool => $state === 'supplier_activity',
                        'gray' => fn ($state): bool => $state === 'invoice_item_activity',
                    ])
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->limit(50),
                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Type d\'objet')
                    ->formatStateUsing(fn ($state) => class_basename($state ?? ''))
                    ->sortable(),
                Tables\Columns\TextColumn::make('causer.name')
                    ->label('Utilisateur')
                    ->default('Système')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('log_name')
                    ->label('Catégorie')
                    ->options([
                        'client_activity' => 'Clients',
                        'product_activity' => 'Produits',
                        'supplier_activity' => 'Fournisseurs',
                        'invoice_activity' => 'Factures',
                        'invoice_item_activity' => 'Lignes de facture',
                    ]),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Depuis le'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Jusqu\'au'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListActivityLogs::route('/'),
            'view' => Pages\ViewActivityLog::route('/{record}'),
        ];
    }
}
