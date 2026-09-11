<?php

declare(strict_types=1);

namespace App\Filament\Resources\TenderNotices\RelationManagers;

use App\Exceptions\AbstractException;
use App\Filament\Resources\TenderNoticeVersions\TenderNoticeVersionResource;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Acquisition\TenderNoticeVersion;
use App\Query\Document\DocumentQueries;
use App\Services\Acquisition\TenderNoticeVersionService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
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
        return __('tender_notice_version.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('tender_notice_version.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        Textarea::make('summary')
                            ->label(__('tender_notice_version.fields.summary'))
                            ->columnSpanFull(),
                        DatePicker::make('published_on')
                            ->label(__('tender_notice_version.fields.published_on'))
                            ->displayFormat('d.m.Y'),
                        Select::make('source_document_revision_id')
                            ->label(__('tender_notice_version.fields.source_document_revision'))
                            ->options(fn (): array => app(DocumentQueries::class)->revisionOptions())
                            ->searchable()
                            ->native(false),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('tender_notice_version.label'))
            ->heading(__('tender_notice_version.relation.title'))
            ->recordTitleAttribute('version_no')
            ->columns([
                TextColumn::make('version_no')
                    ->label(__('tender_notice_version.fields.version_no'))
                    ->sortable(),
                TextColumn::make('published_on')
                    ->label(__('tender_notice_version.fields.published_on'))
                    ->date('d.m.Y')
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label(__('tender_notice_version.fields.status'))
                    ->badge(),
                TextColumn::make('capturer.full_name')
                    ->label(__('tender_notice_version.fields.capturer')),
                TextColumn::make('created_at')
                    ->label(__('tender_notice_version.fields.created_at'))
                    ->dateTime('d.m.Y H:i'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['tender_notice_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(TenderNoticeVersionService::class)->create($data);
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
                    ->url(fn (TenderNoticeVersion $record): string => TenderNoticeVersionResource::getUrl('view', ['record' => $record])),
                EditAction::make()
                    ->using(function (TenderNoticeVersion $record, array $data): Model {
                        try {
                            return app(TenderNoticeVersionService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('version_no', 'desc')
            ->emptyStateHeading(__('tender_notice_version.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedDocumentDuplicate);
    }
}
