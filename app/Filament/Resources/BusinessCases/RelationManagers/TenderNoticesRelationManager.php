<?php

declare(strict_types=1);

namespace App\Filament\Resources\BusinessCases\RelationManagers;

use App\Enums\Acquisition\TenderNoticeStatus;
use App\Exceptions\AbstractException;
use App\Filament\Resources\TenderNotices\TenderNoticeResource;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Acquisition\TenderNotice;
use App\Services\Acquisition\TenderNoticeService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
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

class TenderNoticesRelationManager extends RelationManager
{
    protected static string $relationship = 'tenderNotices';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedMegaphone;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('tender_notice.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            ...FieldGrid::group([
                        Select::make('tender_source_id')
                            ->label(__('tender_notice.fields.tender_source'))
                            ->relationship('source', 'name_tr')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),
                        TextInput::make('title')
                            ->label(__('tender_notice.fields.title'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('external_notice_id')
                            ->label(__('tender_notice.fields.external_notice'))
                            ->maxLength(100),
                        Select::make('issuer_party_id')
                            ->label(__('tender_notice.fields.issuer_party'))
                            ->relationship('issuerParty', 'display_name')
                            ->searchable()
                            ->preload()
                            ->native(false),
                        TextInput::make('notice_url')
                            ->label(__('tender_notice.fields.notice_url'))
                            ->maxLength(2048),
                        Select::make('status')
                            ->label(__('tender_notice.fields.status'))
                            ->options(TenderNoticeStatus::class)
                            ->default(TenderNoticeStatus::Captured->value)
                            ->required()
                            ->native(false),
                        Textarea::make('summary')
                            ->label(__('tender_notice.fields.summary'))
                            ->hiddenOn('edit')
                            ->columnSpanFull(),
                        DatePicker::make('published_on')
                            ->label(__('tender_notice.fields.published_on'))
                            ->displayFormat('d.m.Y')
                            ->hiddenOn('edit'),
                        Hidden::make('row_version')->hiddenOn('create'),
            ], [
                'identity' => ['label' => __('tender_notice.sections.identity'), 'icon' => Heroicon::OutlinedMegaphone, 'fields' => ['tender_source_id', 'title', 'external_notice_id', 'issuer_party_id']],
                'publication' => ['label' => __('tender_notice.sections.publication'), 'icon' => Heroicon::OutlinedGlobeAlt, 'fields' => ['notice_url', 'status', 'published_on']],
                'summary' => ['label' => __('tender_notice.sections.summary'), 'icon' => Heroicon::OutlinedDocumentText, 'fields' => ['summary'], 'visible' => fn (string $operation): bool => $operation === 'create'],
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('tender_notice.label'))
            ->heading(__('tender_notice.relation.title'))
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('title')
                    ->label(__('tender_notice.fields.title'))
                    ->limit(50)
                    ->searchable(),
                TextColumn::make('source.name_tr')
                    ->label(__('tender_notice.fields.tender_source')),
                TextColumn::make('captured_at')
                    ->label(__('tender_notice.fields.captured_at'))
                    ->dateTime('d.m.Y H:i'),
                TextColumn::make('currentVersion.version_no')
                    ->label(__('tender_notice.fields.current_version'))
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label(__('tender_notice.fields.status'))
                    ->badge(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['business_case_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(TenderNoticeService::class)->create($data);
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
                    ->url(fn (TenderNotice $record): string => TenderNoticeResource::getUrl('view', ['record' => $record])),
                EditAction::make()
                    ->using(function (TenderNotice $record, array $data): Model {
                        try {
                            return app(TenderNoticeService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('captured_at', 'desc')
            ->emptyStateHeading(__('tender_notice.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedMegaphone);
    }
}
