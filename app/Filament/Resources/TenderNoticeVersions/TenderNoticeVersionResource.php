<?php

declare(strict_types=1);

namespace App\Filament\Resources\TenderNoticeVersions;

use App\Filament\NavigationGroup;
use App\Filament\Resources\TenderNoticeVersions\Pages\EditTenderNoticeVersion;
use App\Filament\Resources\TenderNoticeVersions\Pages\ListTenderNoticeVersions;
use App\Filament\Resources\TenderNoticeVersions\Pages\ViewTenderNoticeVersion;
use App\Filament\Resources\TenderNoticeVersions\RelationManagers\DeadlinesRelationManager;
use App\Filament\Resources\TenderNoticeVersions\RelationManagers\RequirementsRelationManager;
use App\Filament\Support\FieldGrid;
use App\Models\Acquisition\TenderNoticeVersion;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class TenderNoticeVersionResource extends Resource
{
    protected static ?string $model = TenderNoticeVersion::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static string | UnitEnum | null $navigationGroup = NavigationGroup::Acquisition;

    protected static ?int $navigationSort = 31;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $recordTitleAttribute = 'version_no';

    public static function getModelLabel(): string
    {
        return __('tender_notice_version.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('tender_notice_version.plural');
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
            Section::make(__('tender_notice_version.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        Textarea::make('summary')
                            ->label(__('tender_notice_version.fields.summary'))
                            ->columnSpanFull(),
                ])),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('notice.title')
                    ->label(__('tender_notice_version.fields.notice'))
                    ->limit(40),
                TextColumn::make('version_no')
                    ->label(__('tender_notice_version.fields.version_no')),
                TextColumn::make('published_on')
                    ->label(__('tender_notice_version.fields.published_on'))
                    ->date('d.m.Y')
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label(__('tender_notice_version.fields.status'))
                    ->badge(),
                TextColumn::make('capturer.full_name')
                    ->label(__('tender_notice_version.fields.capturer')),
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
            RequirementsRelationManager::class,
            DeadlinesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTenderNoticeVersions::route('/'),
            'view' => ViewTenderNoticeVersion::route('/{record}'),
            'edit' => EditTenderNoticeVersion::route('/{record}/edit'),
        ];
    }
}
