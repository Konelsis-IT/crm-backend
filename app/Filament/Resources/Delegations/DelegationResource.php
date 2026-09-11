<?php

declare(strict_types=1);

namespace App\Filament\Resources\Delegations;

use App\Enums\Approval\DelegationScopeType;
use App\Enums\Approval\DelegationStatus;
use App\Filament\NavigationGroup;
use App\Filament\Resources\Delegations\Pages\CreateDelegation;
use App\Filament\Resources\Delegations\Pages\EditDelegation;
use App\Filament\Resources\Delegations\Pages\ListDelegations;
use App\Filament\Resources\Delegations\Pages\ViewDelegation;
use App\Filament\Support\FieldGrid;
use App\Models\Approval\Delegation;
use App\Models\Personnel\Personnel;
use App\Query\Approval\ApprovalQueries;
use App\Query\Personnel\PersonnelQueries;
use App\Services\Authorization\RoleResolver;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * Vekaletler (06 SS4.11): onay kararini belirli bir sure baskasina devretme.
 * Idari grupta; personel kendi vekaletini verir, yonetici hepsini gorur.
 */
class DelegationResource extends Resource
{
    protected static ?string $model = Delegation::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static string | UnitEnum | null $navigationGroup = NavigationGroup::Administrative;

    protected static ?int $navigationSort = 40;

    protected static ?string $recordTitleAttribute = 'reason';

    public static function getModelLabel(): string
    {
        return __('delegation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('delegation.plural');
    }

    public static function canAccess(): bool
    {
        return FeatureFlags::enabled('approvals.admin_ui')
            && SchemaReadiness::hasBatch('B07')
            && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        $isAdmin = fn (): bool => auth()->user() instanceof Personnel && app(RoleResolver::class)->isSystemAdmin(auth()->user());

        return $schema->columns(1)->components([
            Section::make(__('delegation.sections.parties'))
                ->description(__('delegation.help.parties'))
                ->icon(Heroicon::OutlinedUserGroup)
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    Select::make('grantor_personnel_id')
                        ->label(__('delegation.fields.grantor'))
                        ->options(fn (): array => app(PersonnelQueries::class)->personnelOptions())
                        ->default(fn (): ?int => auth()->id() !== null ? (int) auth()->id() : null)
                        ->searchable()
                        ->required()
                        ->native(false)
                        ->disabled(fn (): bool => ! $isAdmin())
                        ->dehydrated()
                        ->disabledOn('edit'),
                    Select::make('delegate_personnel_id')
                        ->label(__('delegation.fields.delegate'))
                        ->options(fn (): array => app(PersonnelQueries::class)->personnelOptions())
                        ->searchable()
                        ->required()
                        ->native(false),
                    Select::make('capability_code')
                        ->label(__('delegation.fields.capability_code'))
                        ->options([Delegation::CAPABILITY_APPROVAL_DECIDE => __('delegation.capabilities.approval_decide')])
                        ->default(Delegation::CAPABILITY_APPROVAL_DECIDE)
                        ->required()
                        ->native(false)
                        ->disabledOn('edit')
                        ->dehydrated(),
                    Select::make('scope_type')
                        ->label(__('delegation.fields.scope_type'))
                        ->helperText(__('delegation.help.scope_type'))
                        ->options([
                            DelegationScopeType::All->value => DelegationScopeType::All->getLabel(),
                            DelegationScopeType::ApprovalPolicy->value => DelegationScopeType::ApprovalPolicy->getLabel(),
                        ])
                        ->default(DelegationScopeType::All->value)
                        ->required()
                        ->native(false)
                        ->live(),
                    Select::make('scope_id')
                        ->label(__('delegation.fields.scope_policy'))
                        ->options(fn (): array => app(ApprovalQueries::class)->allPolicyOptions())
                        ->searchable()
                        ->native(false)
                        ->visible(fn (Get $get): bool => $get('scope_type') === DelegationScopeType::ApprovalPolicy->value)
                        ->required(fn (Get $get): bool => $get('scope_type') === DelegationScopeType::ApprovalPolicy->value),
                ])),
            Section::make(__('delegation.sections.validity'))
                ->icon(Heroicon::OutlinedCalendarDays)
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    DateTimePicker::make('valid_from')
                        ->label(__('delegation.fields.valid_from'))
                        ->default(fn (): Carbon => Carbon::now())
                        ->required()
                        ->seconds(false),
                    DateTimePicker::make('valid_until')
                        ->label(__('delegation.fields.valid_until'))
                        ->helperText(__('delegation.help.valid_until'))
                        ->required()
                        ->seconds(false)
                        ->after('valid_from'),
                    Textarea::make('reason')
                        ->label(__('delegation.fields.reason'))
                        ->required()
                        ->rows(2)
                        ->columnSpanFull(),
                    Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('grantor.full_name')
                    ->label(__('delegation.fields.grantor'))
                    ->searchable()
                    ->weight('semibold'),
                TextColumn::make('delegate.full_name')
                    ->label(__('delegation.fields.delegate'))
                    ->searchable(),
                TextColumn::make('scope_type')
                    ->label(__('delegation.fields.scope_type'))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('valid_from')
                    ->label(__('delegation.fields.valid_from'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('valid_until')
                    ->label(__('delegation.fields.valid_until'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('delegation.fields.status'))
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('delegation.fields.status'))
                    ->options(DelegationStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->visible(fn (Delegation $record): bool => in_array($record->status, [DelegationStatus::Pending, DelegationStatus::Active], true)),
            ])
            ->toolbarActions([])
            ->defaultSort('valid_until', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDelegations::route('/'),
            'create' => CreateDelegation::route('/create'),
            'view' => ViewDelegation::route('/{record}'),
            'edit' => EditDelegation::route('/{record}/edit'),
        ];
    }
}
