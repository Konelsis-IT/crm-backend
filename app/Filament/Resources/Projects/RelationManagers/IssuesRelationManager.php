<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\Project\IssueSeverity;
use App\Enums\Project\IssueStatus;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Project\ProjectIssue;
use App\Services\Project\ProjectIssueService;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
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

class IssuesRelationManager extends RelationManager
{
    protected static string $relationship = 'issues';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedExclamationCircle;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('project_issue.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('project_issue.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        TextInput::make('title')
                            ->label(__('project_issue.fields.title'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label(__('project_issue.fields.description'))
                            ->required()
                            ->columnSpanFull(),
                        Select::make('severity')
                            ->label(__('project_issue.fields.severity'))
                            ->options(IssueSeverity::class)
                            ->default(IssueSeverity::Medium->value)
                            ->required()
                            ->native(false),
                        Select::make('status')
                            ->label(__('project_issue.fields.status'))
                            ->options(IssueStatus::class)
                            ->default(IssueStatus::Open->value)
                            ->required()
                            ->native(false),
                        Select::make('workstream_id')
                            ->label(__('project_issue.fields.workstream'))
                            ->relationship(
                            'workstream',
                            'id',
                            modifyQueryUsing: fn ($query) => $query->where('project_id', $this->getOwnerRecord()->getKey()),
                        )
                            ->getOptionLabelFromRecordUsing(fn (\App\Models\Project\ProjectWorkstream $record): string => $record->group->name_tr)
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Select::make('owner_personnel_id')
                            ->label(__('project_issue.fields.owner'))
                            ->relationship('owner', 'full_name')
                            ->searchable()
                            ->preload()
                            ->native(false),
                        DateTimePicker::make('due_at')
                            ->label(__('project_issue.fields.due_at')),
                        Textarea::make('resolution')
                            ->label(__('project_issue.fields.resolution'))
                            ->columnSpanFull(),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('project_issue.label'))
            ->heading(__('project_issue.relation.title'))
            ->recordTitleAttribute('issue_no')
            ->columns([
                TextColumn::make('issue_no')
                    ->label(__('project_issue.fields.issue_no')),
                TextColumn::make('title')
                    ->label(__('project_issue.fields.title'))
                    ->limit(40)
                    ->searchable(),
                TextColumn::make('severity')
                    ->label(__('project_issue.fields.severity'))
                    ->badge(),
                TextColumn::make('status')
                    ->label(__('project_issue.fields.status'))
                    ->badge(),
                TextColumn::make('owner.full_name')
                    ->label(__('project_issue.fields.owner')),
                TextColumn::make('due_at')
                    ->label(__('project_issue.fields.due_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-'),
                TextColumn::make('raised_at')
                    ->label(__('project_issue.fields.raised_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['project_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(ProjectIssueService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (ProjectIssue $record, array $data): Model {
                        try {
                            return app(ProjectIssueService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('raised_at', 'desc')
            ->emptyStateHeading(__('project_issue.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedExclamationCircle);
    }
}
