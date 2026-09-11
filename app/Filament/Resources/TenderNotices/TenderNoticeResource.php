<?php

declare(strict_types=1);

namespace App\Filament\Resources\TenderNotices;

use App\Enums\Acquisition\TenderNoticeStatus;
use App\Filament\NavigationGroup;
use App\Filament\Resources\TenderNotices\Pages\CreateTenderNotice;
use App\Filament\Resources\TenderNotices\Pages\EditTenderNotice;
use App\Filament\Resources\TenderNotices\Pages\ListTenderNotices;
use App\Filament\Resources\TenderNotices\Pages\ViewTenderNotice;
use App\Filament\Resources\TenderNotices\RelationManagers\VersionsRelationManager;
use App\Filament\Support\FieldGrid;
use App\Models\Acquisition\TenderNotice;
use App\Query\Document\DocumentQueries;
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
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class TenderNoticeResource extends Resource
{
    protected static ?string $model = TenderNotice::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string | UnitEnum | null $navigationGroup = NavigationGroup::Acquisition;

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return __('tender_notice.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('tender_notice.plural');
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
            ...FieldGrid::group([
                        Select::make('business_case_id')
                            ->label(__('tender_notice.fields.business_case'))
                            ->relationship('businessCase', 'title')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false)
                            ->disabledOn('edit')
                            ->dehydratedWhenHidden(false),
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
                        Select::make('source_document_revision_id')
                            ->label(__('tender_notice.fields.source_document_revision'))
                            ->options(fn (): array => app(DocumentQueries::class)->revisionOptions())
                            ->searchable()
                            ->native(false)
                            ->hiddenOn('edit'),
                        Hidden::make('row_version')->hiddenOn('create'),
            ], [
                'identity' => ['label' => __('tender_notice.sections.identity'), 'icon' => Heroicon::OutlinedMegaphone, 'fields' => ['business_case_id', 'tender_source_id', 'title', 'external_notice_id', 'issuer_party_id']],
                'publication' => ['label' => __('tender_notice.sections.publication'), 'icon' => Heroicon::OutlinedGlobeAlt, 'fields' => ['notice_url', 'status', 'published_on', 'source_document_revision_id']],
                'summary' => ['label' => __('tender_notice.sections.summary'), 'icon' => Heroicon::OutlinedDocumentText, 'fields' => ['summary'], 'visible' => fn (string $operation): bool => $operation === 'create'],
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('tender_notice.fields.title'))
                    ->limit(50)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('businessCase.title')
                    ->label(__('tender_notice.fields.business_case'))
                    ->limit(30),
                TextColumn::make('source.name_tr')
                    ->label(__('tender_notice.fields.tender_source')),
                TextColumn::make('issuerParty.display_name')
                    ->label(__('tender_notice.fields.issuer_party'))
                    ->placeholder('-'),
                TextColumn::make('captured_at')
                    ->label(__('tender_notice.fields.captured_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('currentVersion.version_no')
                    ->label(__('tender_notice.fields.current_version'))
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label(__('tender_notice.fields.status'))
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('tender_notice.fields.status'))
                    ->options(TenderNoticeStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([])
            ->defaultSort('captured_at', 'desc');
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
            'index' => ListTenderNotices::route('/'),
            'create' => CreateTenderNotice::route('/create'),
            'view' => ViewTenderNotice::route('/{record}'),
            'edit' => EditTenderNotice::route('/{record}/edit'),
        ];
    }
}
