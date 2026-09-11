<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Filament\Resources\Projects\RelationManagers\Concerns\OpensFromChecklist;
use App\Enums\Project\WorkPackageStatus;
use App\Exceptions\AbstractException;
use App\Filament\Resources\WorkPackages\WorkPackageResource;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Project\WorkPackage;
use App\Services\Project\WorkPackageService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class WorkPackagesRelationManager extends RelationManager
{
    use OpensFromChecklist;

    protected static string $relationship = 'workPackages';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedArchiveBox;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('work_package.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('work_package.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        Select::make('project_workstream_id')
                            ->label(__('work_package.fields.project_workstream'))
                            ->relationship(
                            'workstream',
                            'id',
                            modifyQueryUsing: fn ($query) => $query->where('project_id', $this->getOwnerRecord()->getKey()),
                        )
                            ->getOptionLabelFromRecordUsing(fn (\App\Models\Project\ProjectWorkstream $record): string => $record->group->name_tr)
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),
                        TextInput::make('package_code')
                            ->label(__('work_package.fields.package_code'))
                            ->required()
                            ->maxLength(32),
                        TextInput::make('name')
                            ->label(__('work_package.fields.name'))
                            ->required()
                            ->maxLength(255),
                        Select::make('wbs_node_id')
                            ->label(__('work_package.fields.wbs_node'))
                            ->relationship(
                            'wbsNode',
                            'wbs_code',
                            modifyQueryUsing: fn ($query) => $query->where('project_id', $this->getOwnerRecord()->getKey()),
                        )
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Select::make('owner_personnel_id')
                            ->label(__('work_package.fields.owner'))
                            ->relationship('owner', 'full_name')
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Select::make('status')
                            ->label(__('work_package.fields.status'))
                            ->options(WorkPackageStatus::class)
                            ->default(WorkPackageStatus::Planned->value)
                            ->required()
                            ->native(false),
                        DatePicker::make('planned_start_on')
                            ->label(__('work_package.fields.planned_start_on'))
                            ->displayFormat('d.m.Y'),
                        DatePicker::make('planned_finish_on')
                            ->label(__('work_package.fields.planned_finish_on'))
                            ->displayFormat('d.m.Y'),
                        Textarea::make('description')
                            ->label(__('work_package.fields.description'))
                            ->columnSpanFull(),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('work_package.label'))
            ->heading(__('work_package.relation.title'))
            ->recordTitleAttribute('package_code')
            ->columns([
                TextColumn::make('package_code')
                    ->label(__('work_package.fields.package_code'))
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('work_package.fields.name'))
                    ->limit(40),
                TextColumn::make('workstream.group.name_tr')
                    ->label(__('work_package.fields.workstream')),
                TextColumn::make('wbsNode.wbs_code')
                    ->label(__('work_package.fields.wbs_node'))
                    ->placeholder('-'),
                TextColumn::make('owner.full_name')
                    ->label(__('work_package.fields.owner')),
                TextColumn::make('status')
                    ->label(__('work_package.fields.status'))
                    ->badge(),
                TextColumn::make('planned_finish_on')
                    ->label(__('work_package.fields.planned_finish_on'))
                    ->date('d.m.Y')
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {

                        try {
                            return app(WorkPackageService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                Action::make('open')
                    ->label(__('app.actions.open'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (WorkPackage $record): string => WorkPackageResource::getUrl('view', ['record' => $record])),
                EditAction::make()
                    ->using(function (WorkPackage $record, array $data): Model {
                        try {
                            return app(WorkPackageService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                DeleteAction::make()
                    ->using(function (WorkPackage $record): bool {
                        try {
                            return app(WorkPackageService::class)->delete($record);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            return false;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('package_code')
            ->emptyStateHeading(__('work_package.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedArchiveBox);
    }
}
