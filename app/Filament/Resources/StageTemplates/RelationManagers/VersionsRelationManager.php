<?php

declare(strict_types=1);

namespace App\Filament\Resources\StageTemplates\RelationManagers;

use App\Exceptions\AbstractException;
use App\Filament\Resources\StageTemplateVersions\StageTemplateVersionResource;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Project\StageTemplateVersion;
use App\Services\Project\StageTemplateVersionService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
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
        return __('stage_template_version.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('stage_template_version.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        Textarea::make('change_summary')
                            ->label(__('stage_template_version.fields.change_summary'))
                            ->columnSpanFull(),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('stage_template_version.label'))
            ->heading(__('stage_template_version.relation.title'))
            ->recordTitleAttribute('version_no')
            ->columns([
                TextColumn::make('version_no')
                    ->label(__('stage_template_version.fields.version_no'))
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('stage_template_version.fields.status'))
                    ->badge(),
                TextColumn::make('change_summary')
                    ->label(__('stage_template_version.fields.change_summary'))
                    ->limit(50)
                    ->placeholder('-'),
                TextColumn::make('publisher.full_name')
                    ->label(__('stage_template_version.fields.publisher'))
                    ->placeholder('-'),
                TextColumn::make('published_at')
                    ->label(__('stage_template_version.fields.published_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['stage_template_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(StageTemplateVersionService::class)->create($data);
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
                    ->url(fn (StageTemplateVersion $record): string => StageTemplateVersionResource::getUrl('view', ['record' => $record])),
                EditAction::make()
                    ->using(function (StageTemplateVersion $record, array $data): Model {
                        try {
                            return app(StageTemplateVersionService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                Action::make('publish')
                    ->label(__('stage_template_version.actions.publish'))
                    ->color('success')
                    ->icon(Heroicon::OutlinedRocketLaunch)
                    ->requiresConfirmation()
                    ->visible(fn (StageTemplateVersion $record): bool => $record->status === \App\Enums\Project\StageTemplateVersionStatus::Draft)
                    ->action(function (StageTemplateVersion $record, array $data): void {
                        try {
                            app(\App\Services\Project\StageTemplateVersionService::class)->publish($record);
                            DomainNotifications::success(__('stage_template_version.messages.done'));
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('version_no', 'desc')
            ->emptyStateHeading(__('stage_template_version.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedDocumentDuplicate);
    }
}
