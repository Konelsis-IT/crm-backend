<?php

declare(strict_types=1);

namespace App\Filament\Support;

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
use App\Query\Report\WorkItemQueries;
use App\Reports\Work\WorkCategoryCatalog;
use App\Services\Report\WorkItemPresenter;
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

    /**
     * @return array<string, mixed>
     */
    public static function board(): array
    {
        $me = self::me();
        $queries = app(WorkItemQueries::class);
        $scope = (string) request()->query('kapsam', 'mine');

        return [
            ...self::base('work-board'),
            'endpoints' => [
                'board' => self::route('filament.admin.work.board'),
                'dismissed' => self::route('filament.admin.work.dismissed'),
                'store' => self::route('filament.admin.work.items.store'),
                'update' => self::route('filament.admin.work.items.update', ['item' => self::PLACEHOLDER]),
                'status' => self::route('filament.admin.work.items.status', ['item' => self::PLACEHOLDER]),
                'critical' => self::route('filament.admin.work.items.critical', ['item' => self::PLACEHOLDER]),
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
            ],
            'urls' => [
                'reports' => self::safe(fn (): string => ReportResource::getUrl('index')),
                'items' => self::safe(fn (): string => WorkItemResource::getUrl('index')),
            ],
            'scope' => in_array($scope, WorkItemQueries::SCOPES, true) ? $scope : 'mine',
            'project' => filled(request()->query('proje')) ? (int) request()->query('proje') : null,
            'can' => [
                'team' => $me !== null && Gate::forUser($me)->allows('viewTeam', WorkItem::class),
                'all' => $me !== null && Gate::forUser($me)->allows('viewAll', WorkItem::class),
                'create' => $me !== null && Gate::forUser($me)->allows('create', WorkItem::class),
            ],
            'options' => [
                ...self::options(),
                'units' => array_map(
                    fn (int $id, array $unit): array => ['value' => $id, 'label' => $unit['name'], 'code' => $unit['code']],
                    array_keys($units = $queries->units()),
                    $units,
                ),
                'people' => array_map(
                    fn (int $id, array $person): array => ['value' => $id, 'label' => $person['name'], 'unit' => $person['unit'], 'initials' => WorkItemPresenter::initials($person['name'])],
                    array_keys($people = $queries->people()),
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
