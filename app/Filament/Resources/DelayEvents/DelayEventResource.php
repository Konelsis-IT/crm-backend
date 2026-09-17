<?php

declare(strict_types=1);

namespace App\Filament\Resources\DelayEvents;

use App\Enums\Project\DelayCauseCategory;
use App\Enums\Project\DelayStatus;
use App\Filament\Clusters\ProjectGroup;
use App\Filament\Resources\DelayEvents\Pages\EditDelayEvent;
use App\Filament\Resources\DelayEvents\Pages\ListDelayEvents;
use App\Filament\Resources\DelayEvents\Pages\ViewDelayEvent;
use App\Filament\Resources\DelayEvents\RelationManagers\RecoveryActionsRelationManager;
use App\Filament\Support\FieldGrid;
use App\Models\Project\DelayEvent;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DelayEventResource extends Resource
{
    protected static ?string $model = DelayEvent::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $cluster = ProjectGroup::class;

    protected static ?int $navigationSort = 14;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $recordTitleAttribute = 'id';

    public static function getModelLabel(): string
    {
        return __('delay_event.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('delay_event.plural');
    }

    public static function canAccess(): bool
    {
        return FeatureFlags::enabled('projects.admin_ui')
            && SchemaReadiness::hasBatch('B17')
            && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('delay_event.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        DatePicker::make('detected_at')
                            ->label(__('delay_event.fields.detected_at'))
                            ->displayFormat('d.m.Y')
                            ->required(),
                        TextInput::make('delay_days')
                            ->label(__('delay_event.fields.delay_days'))
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                        Select::make('cause_category')
                            ->label(__('delay_event.fields.cause_category'))
                            ->options(DelayCauseCategory::class)
                            ->default(DelayCauseCategory::Other->value)
                            ->required()
                            ->native(false),
                        Textarea::make('description')
                            ->label(__('delay_event.fields.description'))
                            ->required()
                            ->columnSpanFull(),
                        Toggle::make('is_excusable')
                            ->label(__('delay_event.fields.is_excusable')),
                        Select::make('status')
                            ->label(__('delay_event.fields.status'))
                            ->options(DelayStatus::class)
                            ->default(DelayStatus::Open->value)
                            ->required()
                            ->native(false),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('project.name')
                    ->label(__('delay_event.fields.project'))
                    ->limit(30),
                TextColumn::make('detected_at')
                    ->label(__('delay_event.fields.detected_at'))
                    ->dateTime('d.m.Y H:i'),
                TextColumn::make('delay_days')
                    ->label(__('delay_event.fields.delay_days')),
                TextColumn::make('cause_category')
                    ->label(__('delay_event.fields.cause_category'))
                    ->badge(),
                TextColumn::make('status')
                    ->label(__('delay_event.fields.status'))
                    ->badge(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([])
            ->defaultSort('id', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RecoveryActionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDelayEvents::route('/'),
            'view' => ViewDelayEvent::route('/{record}'),
            'edit' => EditDelayEvent::route('/{record}/edit'),
        ];
    }
}
