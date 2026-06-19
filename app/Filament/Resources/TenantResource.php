<?php

namespace App\Filament\Resources;

use App\Enums\TenantPlan;
use App\Enums\TenantStatus;
use App\Filament\Resources\TenantResource\Pages;
use App\Models\Tenant;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Builder;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationLabel = 'Tenants';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255),

            TextInput::make('email')
                ->email()
                ->required()
                ->maxLength(255),

            TextInput::make('gstin')
                ->label('GSTIN')
                ->maxLength(15),

            Select::make('plan')
                ->options(collect(TenantPlan::cases())->pluck('value', 'value'))
                ->required(),

            Select::make('subscription_status')
                ->options(collect(TenantStatus::cases())->pluck('value', 'value'))
                ->required(),

            TextInput::make('rbi_bank_rate')
                ->label('RBI Bank Rate (%)')
                ->numeric()
                ->step(0.25)
                ->minValue(0)
                ->maxValue(25)
                ->required(),

            Toggle::make('is_active')
                ->label('Active'),

            DateTimePicker::make('trial_ends_at')
                ->label('Trial Ends At'),

            DateTimePicker::make('subscription_ends_at')
                ->label('Subscription Ends At'),

            DateTimePicker::make('onboarding_completed_at')
                ->label('Onboarding Completed At'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->searchable(),

                TextColumn::make('plan')
                    ->badge()
                    ->sortable(),

                TextColumn::make('subscription_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active'    => 'success',
                        'trial'     => 'warning',
                        'inactive'  => 'gray',
                        'suspended' => 'danger',
                        default     => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('users_count')
                    ->label('Users')
                    ->counts('users')
                    ->sortable(),

                TextColumn::make('vendors_count')
                    ->label('Vendors')
                    ->counts('vendors')
                    ->sortable(),

                TextColumn::make('trial_ends_at')
                    ->label('Trial Ends')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('subscription_ends_at')
                    ->label('Sub Ends')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Joined')
                    ->date('d M Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('plan')
                    ->options(collect(TenantPlan::cases())->pluck('value', 'value')),

                Tables\Filters\SelectFilter::make('subscription_status')
                    ->label('Status')
                    ->options(collect(TenantStatus::cases())->pluck('value', 'value')),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('suspend')
                    ->label('Suspend')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (Tenant $record): bool => $record->subscription_status !== TenantStatus::Suspended)
                    ->requiresConfirmation()
                    ->action(fn (Tenant $record) => $record->update([
                        'subscription_status' => TenantStatus::Suspended->value,
                        'is_active'           => false,
                    ])),

                Tables\Actions\Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Tenant $record): bool => $record->subscription_status !== TenantStatus::Active)
                    ->action(fn (Tenant $record) => $record->update([
                        'subscription_status' => TenantStatus::Active->value,
                        'is_active'           => true,
                    ])),

                Tables\Actions\Action::make('impersonate')
                    ->label('Impersonate')
                    ->icon('heroicon-o-arrow-right-on-rectangle')
                    ->color('warning')
                    ->url(fn (Tenant $record): string => route('admin.impersonate', $record)),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'edit'  => Pages\EditTenant::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        // Super-admin sees all tenants including soft-deleted ones
        return parent::getEloquentQuery()->withoutGlobalScopes();
    }
}
