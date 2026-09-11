<?php

declare(strict_types=1);

namespace App\Filament\Resources\OperationHandoffs;

use App\Enums\Acquisition\HandoffStatus;
use App\Exceptions\AbstractException;
use App\Filament\NavigationGroup;
use App\Filament\Resources\OperationHandoffs\Pages\CreateOperationHandoff;
use App\Filament\Resources\OperationHandoffs\Pages\EditOperationHandoff;
use App\Filament\Resources\OperationHandoffs\Pages\ListOperationHandoffs;
use App\Filament\Resources\OperationHandoffs\Pages\ViewOperationHandoff;
use App\Filament\Resources\OperationHandoffs\RelationManagers\VersionsRelationManager;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Acquisition\OperationHandoff;
use App\Services\Acquisition\OperationHandoffService;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class OperationHandoffResource extends Resource
{
    protected static ?string $model = OperationHandoff::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedArrowRightCircle;

    protected static string | UnitEnum | null $navigationGroup = NavigationGroup::Acquisition;

    protected static ?int $navigationSort = 60;

    // Menude ayri satir kaplamaz; Is Dosyasi > Operasyona Devir alt listesinden acilir (D-70).
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $recordTitleAttribute = 'id';

    public static function getModelLabel(): string
    {
        return __('operation_handoff.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('operation_handoff.plural');
    }

    public static function canAccess(): bool
    {
        return FeatureFlags::enabled('acquisition.admin_ui')
            && SchemaReadiness::hasBatch('B16')
            && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('operation_handoff.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        Select::make('business_case_id')
                            ->label(__('operation_handoff.fields.business_case'))
                            ->relationship('businessCase', 'title')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false)
                            ->disabledOn('edit')
                            ->dehydratedWhenHidden(false),
                        Select::make('prepared_by_employee_id')
                            ->label(__('operation_handoff.fields.prepared_by'))
                            ->relationship('preparer', 'full_name')
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('businessCase.title')
                    ->label(__('operation_handoff.fields.business_case'))
                    ->limit(40)
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('operation_handoff.fields.status'))
                    ->badge(),
                TextColumn::make('preparer.full_name')
                    ->label(__('operation_handoff.fields.preparer')),
                TextColumn::make('acceptedVersion.version_no')
                    ->label(__('operation_handoff.fields.accepted_version'))
                    ->placeholder('-'),
                TextColumn::make('acceptor.full_name')
                    ->label(__('operation_handoff.fields.acceptor'))
                    ->placeholder('-'),
                TextColumn::make('accepted_at')
                    ->label(__('operation_handoff.fields.accepted_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('operation_handoff.fields.status'))
                    ->options(HandoffStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                ActionGroup::make(self::statusActions())
                    ->label(__('operation_handoff.actions.change_status'))
                    ->icon(Heroicon::OutlinedArrowPath),
            ])
            ->toolbarActions([])
            ->defaultSort('id', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            VersionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOperationHandoffs::route('/'),
            'create' => CreateOperationHandoff::route('/create'),
            'view' => ViewOperationHandoff::route('/{record}'),
            'edit' => EditOperationHandoff::route('/{record}/edit'),
        ];
    }

    /**
     * Izin verilen her hedef durum icin ayri islem (docs/planning/14).
     *
     * @return list<Action>
     */
    private static function statusActions(): array
    {
        $actions = [];

        foreach (HandoffStatus::cases() as $target) {
            if (in_array($target, [HandoffStatus::Accepted], true)) {
                continue;
            }

            $actions[] = Action::make('status_'.$target->value)
                ->label(__('operation_handoff.actions.set_status', ['status' => $target->getLabel()]))
                ->color($target->getColor())
                ->requiresConfirmation()
                ->schema([
                    Textarea::make('reason')
                        ->label(__('operation_handoff.fields.reason'))
                        ->maxLength(500),
                ])
                ->visible(fn (OperationHandoff $record): bool => Gate::allows('update', $record)
                    && $record->status->canTransitionTo($target))
                ->action(function (OperationHandoff $record, array $data) use ($target): void {
                    try {
                        app(OperationHandoffService::class)->changeStatus($record, $target, $data['reason'] ?? null);
                        DomainNotifications::success(__('operation_handoff.messages.status_changed'));
                    } catch (AbstractException $exception) {
                        DomainNotifications::failure($exception);
                    }
                });
        }

        return $actions;
    }
}
