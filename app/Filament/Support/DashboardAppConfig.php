<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\Acquisition\AcquisitionStage;
use App\Enums\Acquisition\OfferStatus;
use App\Enums\Acquisition\ProjectScopeType;
use App\Enums\Platform\Feature;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\BusinessCases\BusinessCaseResource;
use App\Filament\Resources\MeetingPlans\MeetingPlanResource;
use App\Filament\Resources\Parties\PartyResource;
use App\Filament\Resources\Personnel\PersonnelResource;
use App\Filament\Resources\Proposals\ProposalResource;
use App\Models\Acquisition\Proposal;
use App\Models\Personnel\Personnel;
use App\Query\Dashboard\DepartmentDashboardQueries;
use App\Services\Authorization\ExecutiveDirectory;
use App\Services\Platform\FeatureFlags;
use App\Support\Dashboards\DepartmentDashboards;
use App\Support\DisplayTime;
use App\Support\Money;
use BackedEnum;
use Filament\Support\Enums\IconSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Departman panolari React uygulamasinin yapilandirmasi (D-173). Sayfa bunu
 * data-config olarak verir (resources/views/filament/dashboards/app.blade.php);
 * React (resources/js/dashboards/*.js) yalniz okur.
 *
 * - Etiketler lang/{tr,en}/dashboards.php `ui`; durum, proje tipi ve is
 *   durumu adlari enum'lardan (ham kod ekrana gitmez).
 * - Simgeler Heroicon'dan sunucuda SVG olarak uretilir: proje tipi simgesinin
 *   tek kaynagi ProjectScopeType::getIcon() (D-163) korunur.
 * - Veri DepartmentDashboardQueries::snapshot(): tek seferde, salt okunur.
 *   Teklif satirlari yalniz teklif listesini gorme yetkisi olana gider.
 * - Kayit adresleri __ID__ yer tutuculu sablondur (WorkAppConfig ile ayni yol).
 */
final class DashboardAppConfig
{
    private const PLACEHOLDER = 987654321;

    /** Arayuz simgeleri (React adlariyla). */
    private const ICONS = [
        'filter' => Heroicon::OutlinedFunnel,
        'columns' => Heroicon::OutlinedViewColumns,
        'excel' => Heroicon::OutlinedTableCells,
        'pdf' => Heroicon::OutlinedDocumentText,
        'external' => Heroicon::OutlinedArrowTopRightOnSquare,
        'close' => Heroicon::OutlinedXMark,
        'search' => Heroicon::OutlinedMagnifyingGlass,
        'clock' => Heroicon::OutlinedClock,
        'send' => Heroicon::OutlinedPaperAirplane,
        'check' => Heroicon::OutlinedCheckCircle,
        'lost' => Heroicon::OutlinedXCircle,
        'person' => RecordLinks::PERSONNEL_ICON,
        'party' => Heroicon::OutlinedBuildingOffice,
        'case' => Heroicon::OutlinedBriefcase,
        'proposal' => Heroicon::OutlinedClipboardDocumentList,
        'warning' => Heroicon::OutlinedExclamationTriangle,
        'critical' => Heroicon::OutlinedExclamationCircle,
        'info' => Heroicon::OutlinedInformationCircle,
        'chat' => Heroicon::OutlinedChatBubbleLeftRight,
        'up' => Heroicon::OutlinedArrowTrendingUp,
        'down' => Heroicon::OutlinedArrowTrendingDown,
        'chart' => Heroicon::OutlinedChartBar,
        'sort' => Heroicon::OutlinedChevronUpDown,
        'flag' => Heroicon::OutlinedFlag,
        'bell' => Heroicon::OutlinedBell,
        'home' => Heroicon::OutlinedHome,
        'calendar' => Heroicon::OutlinedCalendarDays,
    ];

    /** Teklif durumu simgesi (borsa tipi tek simgeli durum dugmeleri). */
    private const STATUS_ICONS = [
        'to_be_submitted' => 'clock',
        'submitted' => 'send',
        'approved' => 'check',
        'lost' => 'lost',
    ];

    /**
     * Teklif tablosunun sutunlari: anahtar => [varsayilan gorunur mu, disa aktarilir mi].
     * Disa aktarim ayni anahtarlari kullanir (DashboardExportController).
     */
    public const PROPOSAL_COLUMNS = [
        'no' => [true, true],
        'potis' => [true, true],
        'party' => [true, true],
        'title' => [true, true],
        'types' => [true, true],
        'status' => [true, true],
        'offer_date' => [true, true],
        'amount' => [true, true],
        'heat' => [true, true],
        'owner' => [true, true],
        'age' => [true, true],
        'activity' => [true, true],
        'actions' => [true, false],
    ];

    /**
     * @return array<string, mixed>
     */
    public static function make(string $app, ?string $mode = null): array
    {
        $me = auth()->user();
        $me = $me instanceof Personnel ? $me->loadMissing('orgUnit') : null;
        $zone = DisplayTime::zone();
        $labels = __('dashboards.ui');
        $canRows = $me !== null && Gate::forUser($me)->allows('viewAny', Proposal::class);
        $default = DepartmentDashboards::modeFor($me);
        $queries = app(DepartmentDashboardQueries::class);

        return [
            'app' => $app,
            'locale' => app()->getLocale(),
            // D-180: tutarlar ISO kodu yerine simgeyle (App\Support\Money ile ayni tablo).
            'currency_symbols' => Money::SYMBOLS,
            'labels' => is_array($labels) ? $labels : [],
            'today' => Carbon::now($zone)->format('Y-m-d'),
            'generated_at' => Carbon::now($zone)->format('d.m.Y H:i'),
            'viewer' => [
                'id' => $me !== null ? (int) $me->getKey() : null,
                'name' => $me?->full_name,
                'unit' => $me?->orgUnit?->name,
                'default_mode' => $default,
                // D-147: sirket geneli tablo canlida yalniz ust yonetime; onizlemede uyari gosterilir.
                'is_executive' => app(ExecutiveDirectory::class)->isExecutive($me),
                'can_rows' => $canRows,
            ],
            'mode' => DepartmentDashboards::normalize($mode) ?? $default,
            'modes' => DepartmentDashboards::MODES,
            'statuses' => array_map(static fn (OfferStatus $status): array => [
                'value' => $status->value,
                'label' => (string) $status->getLabel(),
                'color' => $status->getColor(),
                'icon' => self::STATUS_ICONS[$status->value] ?? 'flag',
            ], OfferStatus::cases()),
            'types' => array_combine(
                array_map(static fn (ProjectScopeType $type): string => $type->value, ProjectScopeType::cases()),
                array_map(static fn (ProjectScopeType $type): array => [
                    'label' => (string) $type->getLabel(),
                    'color' => $type->getColor(),
                    'icon' => self::svg($type->getIcon()),
                ], ProjectScopeType::cases()),
            ),
            'stages' => array_combine(
                array_map(static fn (AcquisitionStage $stage): string => $stage->value, AcquisitionStage::cases()),
                array_map(static fn (AcquisitionStage $stage): string => (string) $stage->getLabel(), AcquisitionStage::cases()),
            ),
            'icons' => array_map(static fn (Heroicon $icon): string => self::svg($icon), self::ICONS),
            'columns' => self::proposalColumns(),
            'party_columns' => self::partyColumns(),
            'urls' => [
                'proposal' => self::template(fn (): string => ProposalResource::getUrl('view', ['record' => self::PLACEHOLDER])),
                'case' => self::template(fn (): string => BusinessCaseResource::getUrl('view', ['record' => self::PLACEHOLDER])),
                'party' => self::template(fn (): string => PartyResource::getUrl('view', ['record' => self::PLACEHOLDER])),
                'personnel' => self::template(fn (): string => PersonnelResource::getUrl('view', ['record' => self::PLACEHOLDER])),
                'proposals' => self::safe(fn (): string => ProposalResource::getUrl('index')),
                'cases' => self::safe(fn (): string => BusinessCaseResource::getUrl('index')),
                'meetings' => self::safe(fn (): string => MeetingPlanResource::getUrl('index')),
                'dashboard' => self::safe(fn (): string => Dashboard::getUrl()),
            ],
            'export' => [
                'url' => self::safe(fn (): string => route('filament.admin.dashboards.export', absolute: false)),
                'excel' => FeatureFlags::enabled(Feature::UiDashboardExcel),
                'pdf' => FeatureFlags::enabled(Feature::UiDashboardPdf),
            ],
            'thresholds' => [
                'stale_to_submit' => DepartmentDashboardQueries::STALE_TO_SUBMIT_DAYS,
                'stale_submitted' => DepartmentDashboardQueries::STALE_SUBMITTED_DAYS,
            ],
            'data' => $queries->snapshot($canRows),
        ];
    }

    /**
     * @return list<array{key: string, label: string, default: bool, export: bool}>
     */
    public static function proposalColumns(): array
    {
        $out = [];

        foreach (self::PROPOSAL_COLUMNS as $key => [$default, $export]) {
            $out[] = ['key' => $key, 'label' => (string) __('dashboards.columns.'.$key), 'default' => $default, 'export' => $export];
        }

        return $out;
    }

    /**
     * @return list<array{key: string, label: string, default: bool, export: bool}>
     */
    public static function partyColumns(): array
    {
        return array_map(
            static fn (string $key): array => ['key' => $key, 'label' => (string) __('dashboards.party_columns.'.$key), 'default' => true, 'export' => $key !== 'spark'],
            ['party', 'cases', 'proposals', 'submitted', 'lost', 'notes_90', 'spark'],
        );
    }

    /** Heroicon'u satir ici SVG olarak verir (React dangerouslySetInnerHTML; kaynak Filament). */
    private static function svg(Heroicon | BackedEnum | string $icon): string
    {
        try {
            $name = $icon instanceof Heroicon ? $icon->getIconForSize(IconSize::Medium) : (string) ($icon instanceof BackedEnum ? $icon->value : $icon);

            return trim(svg($name)->toHtml());
        } catch (Throwable) {
            return '';
        }
    }

    private static function template(callable $resolve): ?string
    {
        $url = self::safe($resolve);

        return $url === null ? null : str_replace((string) self::PLACEHOLDER, '__ID__', $url);
    }

    private static function safe(callable $resolve): ?string
    {
        try {
            $value = $resolve();

            return is_string($value) ? $value : null;
        } catch (Throwable) {
            return null;
        }
    }
}
