<?php

declare(strict_types=1);

namespace App\Filament\Resources\DepartmentHandoffs\RelationManagers;

use App\Enums\Acquisition\ReviewDecision;
use App\Exceptions\AbstractException;
use App\Filament\Resources\DepartmentHandoffVersions\DepartmentHandoffVersionResource;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Project\DepartmentHandoffVersion;
use App\Services\Project\DepartmentHandoffVersionService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
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

class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedDocumentDuplicate;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('department_handoff_version.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('department_handoff_version.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        TextInput::make('snapshot_hash')
                            ->label(__('department_handoff_version.fields.snapshot_hash'))
                            ->maxLength(64)
                            ->hiddenOn('create'),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('department_handoff_version.label'))
            ->heading(__('department_handoff_version.relation.title'))
            ->recordTitleAttribute('version_no')
            ->columns([
                TextColumn::make('version_no')
                    ->label(__('department_handoff_version.fields.version_no'))
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('department_handoff_version.fields.status'))
                    ->badge(),
                TextColumn::make('submitter.full_name')
                    ->label(__('department_handoff_version.fields.submitter'))
                    ->placeholder('-'),
                TextColumn::make('submitted_at')
                    ->label(__('department_handoff_version.fields.submitted_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['department_handoff_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(DepartmentHandoffVersionService::class)->create($data);
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
                    ->url(fn (DepartmentHandoffVersion $record): string => DepartmentHandoffVersionResource::getUrl('view', ['record' => $record])),
                Action::make('submit')
                    ->label(__('department_handoff_version.actions.submit'))
                    ->color('primary')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->requiresConfirmation()
                    ->visible(fn (DepartmentHandoffVersion $record): bool => $record->status === \App\Enums\Acquisition\HandoffVersionStatus::Draft)
                    ->action(function (DepartmentHandoffVersion $record, array $data): void {
                        try {
                            app(\App\Services\Project\DepartmentHandoffVersionService::class)->submit($record);
                            DomainNotifications::success(__('department_handoff_version.messages.done'));
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);
                        }
                    }),
                Action::make('review')
                    ->label(__('department_handoff_version.actions.review'))
                    ->color('success')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->requiresConfirmation()
                    ->schema([
                Select::make('decision')
                    ->label(__('department_handoff_version.fields.decision'))
                    ->options(ReviewDecision::class)
                    ->default(ReviewDecision::Accepted->value)
                    ->required()
                    ->native(false),
                Textarea::make('comment')
                    ->label(__('department_handoff_version.fields.comment'))
                    ->columnSpanFull(),
                    ])
                    ->visible(fn (DepartmentHandoffVersion $record): bool => $record->status === \App\Enums\Acquisition\HandoffVersionStatus::Submitted)
                    ->action(function (DepartmentHandoffVersion $record, array $data): void {
                        try {
                            app(\App\Services\Project\DepartmentHandoffReviewService::class)->create(['handoff_version_id' => $record->getKey(), 'decision' => $data['decision'], 'comment' => $data['comment'] ?? null]);
                            DomainNotifications::success(__('department_handoff_version.messages.done'));
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('version_no', 'desc')
            ->emptyStateHeading(__('department_handoff_version.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedDocumentDuplicate);
    }
}
