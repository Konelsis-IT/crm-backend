<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Enums\Acquisition\OfferStatus;
use App\Enums\Acquisition\ProjectScopeType;
use App\Enums\Platform\Feature;
use App\Filament\Exports\DashboardTableExport;
use App\Filament\Support\DashboardAppConfig;
use App\Http\Controllers\Controller;
use App\Models\Acquisition\Proposal;
use App\Models\Party\Party;
use App\Models\Personnel\Personnel;
use App\Query\Dashboard\DepartmentDashboardQueries;
use App\Services\Platform\FeatureFlags;
use App\Support\Dashboards\DepartmentDashboards;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Departman panosundaki gorunen tablonun Excel / PDF'i (D-173). Salt okunur.
 *
 * Istek: format=xlsx|pdf, table=proposals|parties, columns[]=..., status,
 * mine=1, q. Sunucu ayni sorguyu (DepartmentDashboardQueries) ayni
 * suzgeclerle yeniden calistirir; yalniz disa aktarilabilir sutunlar yazilir.
 * Yetki: ozellik anahtari (rota + bicim), UI Deneme kurali
 * (DepartmentDashboards::canPreview) ve kaydin listeleme politikasi.
 */
final class DashboardExportController extends Controller
{
    public function __invoke(Request $request, DepartmentDashboardQueries $queries, DashboardTableExport $export): StreamedResponse
    {
        $me = $request->user();
        abort_unless($me instanceof Personnel && DepartmentDashboards::canPreview($me), 403);
        abort_unless($queries->ready(), 404);

        $format = $request->query('format') === 'pdf' ? 'pdf' : 'xlsx';
        abort_unless(FeatureFlags::enabled($format === 'pdf' ? Feature::UiDashboardPdf : Feature::UiDashboardExcel), 404);

        $table = $request->query('table') === 'parties' ? 'parties' : 'proposals';
        $requested = array_values(array_filter((array) $request->query('columns', []), 'is_string'));

        [$title, $columns, $rows, $facts] = $table === 'parties'
            ? $this->parties($me, $queries, $requested)
            : $this->proposals($request, $me, $queries, $requested);

        return $format === 'pdf'
            ? $export->pdf($title, $columns, $rows, $facts)
            : $export->xlsx($title, $columns, $rows, $facts);
    }

    /**
     * @param  list<string>  $requested
     * @return array{0: string, 1: list<array{key: string, label: string}>, 2: list<array<string, string>>, 3: list<array{label: string, value: string}>}
     */
    private function proposals(Request $request, Personnel $me, DepartmentDashboardQueries $queries, array $requested): array
    {
        Gate::forUser($me)->authorize('viewAny', Proposal::class);

        $columns = $this->columns(DashboardAppConfig::proposalColumns(), $requested);
        $status = OfferStatus::tryFrom((string) $request->query('status', ''));
        $mine = filter_var($request->query('mine', '0'), FILTER_VALIDATE_BOOLEAN);
        $search = trim((string) $request->query('q', ''));

        $rows = array_map(fn (array $row): array => [
            'no' => $row['no'],
            'potis' => (string) ($row['potis'] ?? ''),
            'party' => $row['party'],
            'title' => $row['title'],
            'types' => implode(', ', array_map(static fn (string $type): string => (string) (ProjectScopeType::tryFrom($type)?->getLabel() ?? $type), $row['types'])),
            'status' => (string) (OfferStatus::tryFrom($row['status'])?->getLabel() ?? ''),
            'offer_date' => $row['offer_date'] !== null ? Carbon::parse($row['offer_date'])->format('d.m.Y') : '',
            'amount' => $row['amount'] !== null ? $this->money((float) $row['amount'], $row['currency']) : (string) __('dashboards.export.no_amount'),
            'heat' => '%'.$row['heat'],
            'owner' => (string) ($row['owner'] ?? ''),
            'age' => (string) $row['age'],
            'activity' => (string) __('dashboards.export.notes_total', ['count' => array_sum($row['spark'])]),
        ], $queries->proposalRows($status?->value, $search !== '' ? $search : null, $mine ? (int) $me->getKey() : null));

        $facts = [
            ['label' => __('dashboards.export.status'), 'value' => $status !== null ? (string) $status->getLabel() : (string) __('dashboards.export.status_all')],
            ['label' => __('dashboards.export.owner'), 'value' => (string) __($mine ? 'dashboards.export.owner_mine' : 'dashboards.export.owner_all')],
        ];

        if ($search !== '') {
            $facts[] = ['label' => __('dashboards.export.search'), 'value' => $search];
        }

        $facts[] = ['label' => __('dashboards.export.columns'), 'value' => implode(', ', array_map(static fn (array $column): string => $column['label'], $columns))];

        return [(string) __('dashboards.export.proposals'), $columns, $rows, $facts];
    }

    /**
     * @param  list<string>  $requested
     * @return array{0: string, 1: list<array{key: string, label: string}>, 2: list<array<string, string>>, 3: list<array{label: string, value: string}>}
     */
    private function parties(Personnel $me, DepartmentDashboardQueries $queries, array $requested): array
    {
        Gate::forUser($me)->authorize('viewAny', Party::class);

        $columns = $this->columns(DashboardAppConfig::partyColumns(), $requested);
        $rows = array_map(static fn (array $row): array => [
            'party' => $row['party_full'],
            'cases' => (string) $row['cases'],
            'proposals' => (string) $row['proposals'],
            'submitted' => (string) $row['submitted'],
            'lost' => (string) $row['lost'],
            'notes_90' => (string) $row['notes_90'],
        ], $queries->parties());

        $facts = [['label' => __('dashboards.export.columns'), 'value' => implode(', ', array_map(static fn (array $column): string => $column['label'], $columns))]];

        return [(string) __('dashboards.export.parties'), $columns, $rows, $facts];
    }

    /**
     * Istenen ve disa aktarilabilir sutunlar, tanim sirasiyla; istek bossa varsayilanlar.
     *
     * @param  list<array{key: string, label: string, default: bool, export: bool}>  $definitions
     * @param  list<string>  $requested
     * @return list<array{key: string, label: string}>
     */
    private function columns(array $definitions, array $requested): array
    {
        $picked = array_values(array_filter(
            $definitions,
            static fn (array $column): bool => $column['export'] && ($requested === [] ? $column['default'] : in_array($column['key'], $requested, true)),
        ));

        return array_map(static fn (array $column): array => ['key' => $column['key'], 'label' => $column['label']], $picked);
    }

    /** Tutar + para birimi simgesi (D-180, Money::format; ISO kodu yazilmaz). */
    private function money(float $amount, string $currency): string
    {
        return Money::format($amount, $currency);
    }
}
