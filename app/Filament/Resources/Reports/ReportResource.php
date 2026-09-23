<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reports;

use App\Filament\NavigationGroup;
use App\Filament\Resources\Reports\Pages\CreateReport;
use App\Filament\Resources\Reports\Pages\EditReport;
use App\Filament\Resources\Reports\Pages\ListReports;
use App\Filament\Resources\Reports\Pages\ViewReport;
use App\Filament\Resources\Reports\Schemas\ReportForm;
use App\Filament\Resources\Reports\Schemas\ReportInfolist;
use App\Filament\Resources\Reports\Tables\ReportTable;
use App\Models\Report\Report;
use App\Query\Report\ReportQueries;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Raporlar (D-86): personel taslak secer, taslak formu ve gorunumu belirler
 * (pano / yorumsal / sayisal / degerlendirme). Rapor personel, proje,
 * urun-bilesen, teklif ya da is dosyasina baglanabilir. Menude ust seviyede;
 * rozet inceleme kutusu.
 */
class ReportResource extends Resource
{
    protected static ?string $model = Report::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static ?int $navigationSort = -1;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return __('report.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('report.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('report.plural');
    }

    /**
     * Is panosu (B36, D-115) uygulaninca Raporlar grubunun ilk ogesi; oncesinde
     * menunun ust seviyesinde kalir.
     */
    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return SchemaReadiness::hasBatch('B36') ? NavigationGroup::Reports : null;
    }

    public static function canAccess(): bool
    {
        return FeatureFlags::enabled('reports.admin_ui')
            && SchemaReadiness::hasBatch('B10A')
            && parent::canAccess();
    }

    public static function getNavigationBadge(): ?string
    {
        $personnelId = auth()->id();

        if ($personnelId === null || ! static::canAccess()) {
            return null;
        }

        $count = app(ReportQueries::class)->reviewInboxCount((int) $personnelId);

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return ReportForm::make($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ReportInfolist::make($schema);
    }

    public static function table(Table $table): Table
    {
        return ReportTable::make($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReports::route('/'),
            'create' => CreateReport::route('/create'),
            'view' => ViewReport::route('/{record}'),
            'edit' => EditReport::route('/{record}/edit'),
        ];
    }
}
