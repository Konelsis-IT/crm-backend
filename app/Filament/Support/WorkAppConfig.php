<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\Report\WorkItemPriority;
use App\Enums\Report\WorkDiscipline;
use App\Enums\Platform\Feature;
use App\Enums\Report\WorkItemLinkKind;
use App\Enums\Report\WorkItemSource;
use App\Enums\Report\WorkItemStatus;
use App\Enums\Report\WorkWaitingKind;
use App\Filament\Pages\Work\WorkDurationReport;
use App\Filament\Resources\Personnel\PersonnelResource;
use App\Filament\Resources\Reports\ReportResource;
use App\Filament\Resources\WorkItems\WorkItemResource;
use App\Models\Personnel\Personnel;
use App\Models\Report\WorkItem;
use App\Query\Personnel\OrganizationQueries;
use App\Query\Report\WorkItemQueries;
use App\Reports\Work\WorkCategoryCatalog;
use App\Services\Authorization\ExecutiveDirectory;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Services\Report\WorkItemPresenter;
use App\Services\Report\WorkItemSentence;
use App\Support\DisplayTime;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Is panosu React ekranlarinin (B36, D-115) sayfayla tasinan yapilandirmasi;
 * `data-config` olarak yazilir (resources/views/filament/work/app.blade.php).
 * Sosyal medya cekirdegi (KS) api / csrf / etiketleri ayni bicimde okur; is
 * panosu cekirdegi (KW, resources/js/work/work-core.js) uclari `endpoints`,
 * secim listelerini `options` altindan alir. Ham kod ekrana gitmez: her
 * secenegin Turkce etiketi burada gonderilir.
 */
final class WorkAppConfig
{
    /** Uc adindaki kayit kimligi yer tutucusu (istemci __ID__ ile degistirir). */
    private const PLACEHOLDER = 987654321;

    /** work-core.js'in kendi metinleri (ust cubuk penceresi yalniz bunlari ve qa_* tasir). */
    private const CORE_LABELS = [
        'cancel', 'clear', 'close', 'error_generic', 'forbidden', 'loading', 'network_error', 'no_results',
        'retry', 'search', 'select', 'session_expired', 'stale', 'yes', 'save', 'saved',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function board(): array
    {
        $me = self::me();
        $queries = app(WorkItemQueries::class);
        $scope = (string) request()->query('kapsam', 'mine');
        $units = $queries->units();
        $people = $queries->people();
        $sets = self::personSets($people, $units);

        return [
            ...self::base('work-board'),
            'endpoints' => [
                'board' => self::route('filament.admin.work.board'),
                'dismissed' => self::route('filament.admin.work.dismissed'),
                'store' => self::route('filament.admin.work.items.store'),
                'update' => self::route('filament.admin.work.items.update', ['item' => self::PLACEHOLDER]),
                'status' => self::route('filament.admin.work.items.status', ['item' => self::PLACEHOLDER]),
                'critical' => self::route('filament.admin.work.items.critical', ['item' => self::PLACEHOLDER]),
                'priority' => self::route('filament.admin.work.items.priority', ['item' => self::PLACEHOLDER]),
                'delete' => self::route('filament.admin.work.items.delete', ['item' => self::PLACEHOLDER]),
                'reorder' => self::route('filament.admin.work.reorder'),
                'card' => self::route('filament.admin.work.suggestions.card', ['activity' => self::PLACEHOLDER]),
                'dismiss' => self::route('filament.admin.work.suggestions.dismiss', ['activity' => self::PLACEHOLDER]),
                'restore' => self::route('filament.admin.work.suggestions.restore', ['activity' => self::PLACEHOLDER]),
                'links' => self::route('filament.admin.work.lookup.links'),
                'parties' => self::route('filament.admin.work.lookup.parties'),
                'day' => self::route('filament.admin.work.day'),
                'day_close' => self::route('filament.admin.work.day.close'),
                'week' => self::route('filament.admin.work.week'),
                'week_close' => self::route('filament.admin.work.week.close'),
                'freeze' => self::route('filament.admin.work.freeze'),
                'quick_options' => FeatureFlags::enabled(Feature::WorkQuickAdd) ? self::route('filament.admin.work.quick.options') : null,
                'quick_search' => FeatureFlags::enabled(Feature::WorkQuickAdd) ? self::route('filament.admin.work.quick.search') : null,
                'show' => self::route('filament.admin.work.items.show', ['item' => self::PLACEHOLDER]),
                ...self::threadEndpoints(),
                ...self::historyEndpoints(),
            ],
            'urls' => [
                'reports' => self::safe(fn (): string => ReportResource::getUrl('index')),
                'items' => self::safe(fn (): string => WorkItemResource::getUrl('index')),
            ],
            'scope' => in_array($scope, WorkItemQueries::SCOPES, true) ? $scope : 'mine',
            'project' => filled(request()->query('proje')) ? (int) request()->query('proje') : null,
            // Parca anahtarlari (D-128): React ekrani kapali olani cizmez. D-167 sade
            // gorunum (work.board.compact) kapaliyken pano eski gorunumuyle cizilir.
            'features' => [
                'compact' => FeatureFlags::enabled(Feature::WorkBoardCompact),
                // D-219: kritiklik, coklu sorumlu, Tum ekip, is grubu (B57 + work.items.extended).
                'extended' => WorkItemQueries::extendedUi(),
                // D-233: Hizli is ekle ve ise birden fazla bagli kayit (B59).
                'quick_add' => FeatureFlags::enabled(Feature::WorkQuickAdd),
                // D-237: Raporlastir, kapsam renkleri, Panoyu dondur'un kalkmasi (2.9.1; D-244: 2.9.2).
                'refresh' => FeatureFlags::enabled(Feature::WorkBoardRefresh),
                'multi_links' => WorkItemQueries::multiLinks(),
                // D-235: Akilli is ekle'deki bagli kayit turleri (satin alma yayina girdikce)
                // ve birden fazla baglanabilen turler (B59 / B60).
                'link_types' => self::quickLinkTypes(),
                'multi_kinds' => self::multiKinds(),
                // D-236: sesle yazma (Akilli is ekle'nin alt ozelligi).
                'voice' => FeatureFlags::enabled(Feature::WorkVoice),
            ],
            'can' => [
                'team' => $me !== null && Gate::forUser($me)->allows('viewTeam', WorkItem::class),
                'all' => $me !== null && Gate::forUser($me)->allows('viewAll', WorkItem::class),
                'create' => $me !== null && Gate::forUser($me)->allows('create', WorkItem::class),
            ],
            'options' => [
                ...self::options(),
                // D-218: birden fazla departmanda calisanin birlesik seti ("UNITS:...").
                'category_sets' => [
                    ...app(WorkCategoryCatalog::class)->sets(),
                    ...array_combine(
                        $keys = array_values(array_unique(array_filter($sets))),
                        array_map(fn (string $key): array => app(WorkCategoryCatalog::class)->codesForKey($key), $keys),
                    ),
                ],
                'units' => array_map(
                    fn (int $id, array $unit): array => ['value' => $id, 'label' => $unit['name'], 'code' => $unit['code']],
                    array_keys($units),
                    $units,
                ),
                // D-217 / D-218: kendi kategori seti olan kiside `set` (gorev "POS:YKB",
                // birden fazla departman "UNITS:IS_GELISTIRME+TEKLIF").
                'people' => array_map(
                    fn (int $id, array $person): array => [
                        'value' => $id,
                        'label' => $person['name'],
                        'unit' => $person['unit'],
                        'initials' => WorkItemPresenter::initials($person['name']),
                        'set' => $sets[$id] ?? null,
                    ],
                    array_keys($people),
                    $people,
                ),
                'projects' => array_map(
                    fn (int $id, string $name): array => ['value' => $id, 'label' => $name],
                    array_keys($projects = $queries->projectNames()),
                    $projects,
                ),
            ],
        ];
    }

    /**
     * Kisi => kategori seti anahtari (gorev seti ya da birden fazla departmanin
     * birlesimi); departman seti yetenler listede yok.
     *
     * @param  array<int, array{name: string, unit: int|null}>  $people
     * @param  array<int, array{name: string, code: string}>  $units
     * @return array<int, string>
     */
    private static function personSets(array $people, array $units): array
    {
        $catalog = app(WorkCategoryCatalog::class);
        $positions = app(ExecutiveDirectory::class)->positionCodes();
        $assigned = app(OrganizationQueries::class)->assignmentUnitCodes();
        $sets = [];

        foreach ($people as $id => $person) {
            $own = $person['unit'] !== null ? ($units[$person['unit']]['code'] ?? null) : null;
            $key = $catalog->personKey(array_values(array_filter([$own, ...($assigned[$id] ?? [])])), $positions[$id] ?? null);

            if ($key !== null) {
                $sets[$id] = $key;
            }
        }

        return $sets;
    }

    /**
     * @return array<string, mixed>
     */
    public static function matrix(): array
    {
        $me = self::me();
        $canFill = $me !== null && Gate::forUser($me)->allows('fillControl', WorkItem::class);

        return [
            ...self::base('work-matrix'),
            'endpoints' => [
                'matrix' => self::route('filament.admin.work.matrix'),
                'save' => self::route('filament.admin.work.matrix.save'),
            ],
            'urls' => [
                'reports' => self::safe(fn (): string => ReportResource::getUrl('index')),
                'personnel' => str_replace((string) self::PLACEHOLDER, '__ID__', (string) self::safe(fn (): string => PersonnelResource::getUrl('view', ['record' => self::PLACEHOLDER]))),
            ],
            'can' => ['fill' => $canFill],
            'mode' => in_array(request()->query('gorunum'), ['gun', 'hafta'], true)
                ? (request()->query('gorunum') === 'hafta' ? 'week' : 'day')
                : ($canFill ? 'day' : 'week'),
            'day' => filled(request()->query('gun')) ? (string) request()->query('gun') : null,
            'week' => filled(request()->query('hafta')) ? (string) request()->query('hafta') : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function analysis(): array
    {
        $queries = app(WorkItemQueries::class);

        return [
            ...self::base('work-analysis'),
            'endpoints' => [
                'analysis' => self::route('filament.admin.work.analysis'),
            ],
            'urls' => [
                'duration' => self::safe(fn (): string => WorkDurationReport::getUrl()),
                'items' => self::safe(fn (): string => WorkItemResource::getUrl('index')),
                'item' => str_replace((string) self::PLACEHOLDER, '__ID__', (string) self::safe(fn (): string => WorkItemResource::getUrl('view', ['record' => self::PLACEHOLDER]))),
            ],
            'month' => WorkItemQueries::today()->format('Y-m'),
            'months' => self::months(),
            'options' => [
                'units' => array_map(
                    fn (int $id, array $unit): array => ['value' => $id, 'label' => $unit['name']],
                    array_keys($units = $queries->units()),
                    $units,
                ),
                'projects' => array_map(
                    fn (int $id, string $name): array => ['value' => $id, 'label' => $name],
                    array_keys($projects = $queries->projectNames()),
                    $projects,
                ),
                'statuses' => self::statusOptions(),
            ],
        ];
    }

    /**
     * Personel kartindaki Dikkat karti.
     *
     * @return array<string, mixed>
     */
    public static function attention(Personnel $subject): array
    {
        return [
            ...self::base('work-attention'),
            'endpoints' => [
                'attention' => self::route('filament.admin.work.attention', ['personnel' => (int) $subject->getKey()]),
            ],
            'subject' => ['id' => (int) $subject->getKey(), 'name' => (string) $subject->full_name],
        ];
    }

    /**
     * Ortak alanlar: KS cekirdeginin okudugu anahtarlar (csrf, locale, labels, timezone).
     *
     * @return array<string, mixed>
     */
    /**
     * Isler listesi (D-240, React; resources/js/work/work-list.js): liste ve
     * arsiv uclari, suzgec secim pencereleri icin oneri uclari.
     *
     * @return array<string, mixed>
     */
    public static function workList(): array
    {
        $me = self::me();

        return [
            ...self::base('work-list'),
            'endpoints' => [
                'list' => self::route('filament.admin.work.items.list'),
                'archive' => self::route('filament.admin.work.items.archive', ['item' => self::PLACEHOLDER]),
                'unarchive' => self::route('filament.admin.work.items.unarchive', ['item' => self::PLACEHOLDER]),
                'quick_options' => self::route('filament.admin.work.quick.options'),
                'quick_search' => self::route('filament.admin.work.quick.search'),
            ],
            'features' => [
                'extended' => WorkItemQueries::extendedUi(),
                'multi_links' => WorkItemQueries::multiLinks(),
                'link_types' => self::quickLinkTypes(),
                'multi_kinds' => self::multiKinds(),
                // D-240: Arsiv suzgeci ve Arsivle (B61).
                'archive' => SchemaReadiness::hasBatch('B61'),
                // D-244: satirdaki cevap / belge sayilari cevaplar ve belgelerle (2.9.1) gelir.
                'thread' => self::threadOn(),
            ],
            'can' => [
                'team' => $me !== null && Gate::forUser($me)->allows('viewTeam', WorkItem::class),
                'all' => $me !== null && Gate::forUser($me)->allows('viewAll', WorkItem::class),
            ],
            'options' => [...self::options(), 'per_page' => WorkItemQueries::LIST_PER_PAGE],
        ];
    }

    /**
     * Ust cubuktaki "Akilli is" dugmesinin her sayfadaki penceresi (D-235;
     * kullanici: "üstte İş panosu, Şablonlar ortasına ilgili akıllı iş ekle
     * butonu eklenecek"). Pano verisi yok; yalniz pencerenin ihtiyaci.
     *
     * @return array<string, mixed>
     */
    public static function quick(): array
    {
        $me = self::me();
        $base = self::base('work-quick');
        // Her sayfaya yazilir: yalniz pencerenin ve cekirdegin etiketleri.
        $base['labels'] = array_filter(
            $base['labels'],
            static fn (mixed $value, string $key): bool => str_starts_with($key, 'qa_') || in_array($key, self::CORE_LABELS, true),
            ARRAY_FILTER_USE_BOTH,
        );

        return [
            ...$base,
            'endpoints' => [
                'store' => self::route('filament.admin.work.items.store'),
                'update' => self::route('filament.admin.work.items.update', ['item' => self::PLACEHOLDER]),
                'show' => self::route('filament.admin.work.items.show', ['item' => self::PLACEHOLDER]),
                'quick_options' => self::route('filament.admin.work.quick.options'),
                'quick_search' => self::route('filament.admin.work.quick.search'),
                ...self::threadEndpoints(),
                ...self::historyEndpoints(),
            ],
            'features' => [
                'extended' => WorkItemQueries::extendedUi(),
                'quick_add' => true,
                'multi_links' => WorkItemQueries::multiLinks(),
                'link_types' => self::quickLinkTypes(),
                'multi_kinds' => self::multiKinds(),
                // D-236: sesle yazma (Akilli is ekle'nin alt ozelligi).
                'voice' => FeatureFlags::enabled(Feature::WorkVoice),
            ],
            'can' => [
                'team' => $me !== null && Gate::forUser($me)->allows('viewTeam', WorkItem::class),
                'all' => $me !== null && Gate::forUser($me)->allows('viewAll', WorkItem::class),
                'create' => $me !== null && Gate::forUser($me)->allows('create', WorkItem::class),
            ],
            'options' => self::options(),
        ];
    }

    /**
     * Akilli is ekle bu istekte acik mi (D-235): oturum, B36, Is panosu ve
     * Akilli is ekle ozellikleri. Acikken her sayfada pencere koku cizilir,
     * Isler listesi tek sutunlu okunusa gecer ve is detay sayfasina gidilmez.
     */
    public static function smartOn(): bool
    {
        return self::me() !== null
            && SchemaReadiness::hasBatch('B36')
            && FeatureFlags::enabled(Feature::WorkBoard)
            && FeatureFlags::enabled(Feature::WorkQuickAdd);
    }

    /** Ust cubuk dugmesi: Akilli is ekle acik ve kisi is yazabiliyor. */
    public static function quickAvailable(): bool
    {
        $me = self::me();

        return $me !== null && self::smartOn() && Gate::forUser($me)->allows('create', WorkItem::class);
    }

    /**
     * Akilli is ekle'de sunulan bagli kayit turleri (rozet turu); belge, talep,
     * gorusme her zaman, satin alma turleri ozellik acilinca (D-235).
     *
     * @return list<string>
     */
    private static function quickLinkTypes(): array
    {
        $types = [];

        foreach ([WorkItemLinkKind::Document, WorkItemLinkKind::WorkRequest, WorkItemLinkKind::MeetingPlan, WorkItemLinkKind::PurchaseNeed, WorkItemLinkKind::PurchaseOrder, WorkItemLinkKind::CatalogItem] as $kind) {
            if ($kind->visible()) {
                $types[] = WorkItemSentence::LINK_TYPES[$kind->value];
            }
        }

        return $types;
    }

    /**
     * Birden fazla baglanabilen turler, rozet adiyla (D-233, D-235).
     *
     * @return list<string>
     */
    private static function multiKinds(): array
    {
        return array_values(array_map(
            static fn (WorkItemLinkKind $kind): string => WorkItemSentence::LINK_TYPES[$kind->value] ?? $kind->value,
            WorkItemLinkKind::multiple(),
        ));
    }

    private static function base(string $app): array
    {
        $me = self::me();
        $zone = DisplayTime::zone();
        $labels = __('work_item.ui');

        return [
            'app' => $app,
            'csrf' => csrf_token(),
            'locale' => app()->getLocale(),
            'labels' => is_array($labels) ? $labels : [],
            'timezone' => $zone,
            'today' => WorkItemQueries::today()->format('Y-m-d'),
            'now' => Carbon::now($zone)->format('H:i'),
            'me' => $me !== null ? [
                'id' => (int) $me->getKey(),
                'name' => (string) $me->full_name,
                'initials' => WorkItemPresenter::initials((string) $me->full_name),
                'unit' => $me->org_unit_id !== null ? (int) $me->org_unit_id : null,
                'unit_code' => $me->orgUnit?->code,
                'unit_name' => $me->orgUnit?->name,
                // D-217: kisinin gorevinin kendi kategori seti ("POS:YKB"); yoksa null.
                'set' => app(WorkCategoryCatalog::class)->personKey(app(OrganizationQueries::class)->unitCodesOf((int) $me->getKey()), app(ExecutiveDirectory::class)->positionCodes()[(int) $me->getKey()] ?? null),
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function options(): array
    {
        $categories = app(WorkCategoryCatalog::class);

        return [
            'statuses' => self::statusOptions(),
            'sources' => array_map(fn (WorkItemSource $source): array => ['value' => $source->value, 'label' => $source->getLabel()], WorkItemSource::cases()),
            'waiting_kinds' => array_map(fn (WorkWaitingKind $kind): array => ['value' => $kind->value, 'label' => $kind->getLabel()], WorkWaitingKind::cases()),
            'link_kinds' => array_map(
                fn (string $value, string $label): array => ['value' => $value, 'label' => $label],
                array_keys($links = WorkItemLinkKind::linkOptions()),
                $links,
            ),
            'category_sets' => $categories->sets(),
            // D-219: kritiklik (dusukten kritige) ve is grubu.
            'priorities' => array_map(fn (WorkItemPriority $priority): array => ['value' => $priority->value, 'label' => $priority->getLabel()], WorkItemPriority::cases()),
            // D-245: is grubu kapaliyken bos liste (React secim, oneri ve suzgec gostermez).
            'disciplines' => WorkItemQueries::disciplineUi() ? array_map(fn (WorkDiscipline $discipline): array => ['value' => $discipline->value, 'label' => $discipline->getLabel()], WorkDiscipline::cases()) : [],
            'category_labels' => array_combine($categories->allCodes(), array_map(fn (string $code): string => (string) $categories->label($code), $categories->allCodes())),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private static function statusOptions(): array
    {
        return array_map(fn (WorkItemStatus $status): array => ['value' => $status->value, 'label' => $status->getLabel()], WorkItemStatus::cases());
    }

    /**
     * Son 12 ay (analiz panosu donem secimi).
     *
     * @return list<array{value: string, label: string}>
     */
    private static function months(): array
    {
        $start = WorkItemQueries::today()->startOfMonth();
        $months = [];

        for ($i = 0; $i < 12; $i++) {
            $month = $start->copy()->subMonthsNoOverflow($i);
            $months[] = ['value' => $month->format('Y-m'), 'label' => $month->copy()->locale(app()->getLocale())->translatedFormat('F Y')];
        }

        return $months;
    }

    private static function me(): ?Personnel
    {
        $user = auth()->user();

        return $user instanceof Personnel ? $user->loadMissing('orgUnit') : null;
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    /**
     * Isin cevaplari ve belgeleri (D-241): Akilli is penceresinin okuma
     * gorunumu; is ayrintilari (work.items.extended) ya da cevaplar ve belgeler
     * (D-244, work.board.quick_add.thread, 2.9.1) kapaliyken bos. Bos olunca
     * okuma penceresinde yalniz is gorunur, yazarken belge eklenmez.
     *
     * @return array<string, string|null>
     */
    private static function threadEndpoints(): array
    {
        if (! self::threadOn()) {
            return [];
        }

        return [
            'thread' => self::route('filament.admin.work.items.thread', ['item' => self::PLACEHOLDER]),
            'replies' => self::route('filament.admin.work.items.replies', ['item' => self::PLACEHOLDER]),
            'files' => self::route('filament.admin.work.items.files', ['item' => self::PLACEHOLDER]),
            'file_remove' => self::route('filament.admin.work.items.files.remove', ['file' => self::PLACEHOLDER]),
        ];
    }

    /** Cevaplar ve belgeler acik mi (D-244): is ayrintilari + kendi anahtari. */
    public static function threadOn(): bool
    {
        return WorkItemQueries::extendedUi() && FeatureFlags::enabled(Feature::WorkItemThread);
    }

    /**
     * Isin Gecmis sekmesi (D-248, 2.9.1); kapaliyken uc yazilmaz, sekme cizilmez.
     *
     * @return array<string, string|null>
     */
    private static function historyEndpoints(): array
    {
        return FeatureFlags::enabled(Feature::WorkItemHistory)
            ? ['history' => self::route('filament.admin.work.items.history', ['item' => self::PLACEHOLDER])]
            : [];
    }

    private static function route(string $name, array $parameters = []): ?string
    {
        $url = self::safe(fn (): string => route($name, $parameters, absolute: false));

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
