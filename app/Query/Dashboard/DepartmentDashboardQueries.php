<?php

declare(strict_types=1);

namespace App\Query\Dashboard;

use App\Enums\Acquisition\AcquisitionStage;
use App\Enums\Acquisition\BusinessCodeKind;
use App\Enums\Acquisition\BusinessOutcome;
use App\Enums\Acquisition\OfferStatus;
use App\Enums\Acquisition\ProjectScopeType;
use App\Models\Personnel\Personnel;
use App\Query\Acquisition\BusinessCaseQueries;
use App\Services\Authorization\SystemAccount;
use App\Services\Platform\SchemaReadiness;
use App\Support\Acquisition\ScopeTypes;
use App\Support\DisplayTime;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Departman panolari okuma sorgulari (D-173, 8 Ekim 2026 kullanici istegi:
 * Teklif / Is Gelistirme ve Yonetici panolari, borsa ekrani gibi yogun).
 *
 * Yalniz okur; toplamlar SQL'de alinir (gun / hafta / ay kovalari, durum ve
 * para birimi kirilimi). Tarihler veritabaninda UTC'dir; kovalar kurum saatine
 * gore (DisplayTime) kaydirilarak hesaplanir.
 *
 * Para (D-173): tutarlar uydurulmaz ve cevrilmez (kur tablosu yok). Her toplam
 * para birimine gore ayri verilir ve kac kayitta tutar girildigi ("kapsam")
 * birlikte doner; tutari bos kayit sifir sayilmaz, sayisi ayrica gosterilir.
 * - Potansiyel is degeri: business_cases.estimated_value / currency_code.
 * - Teklif tutari: guncel surumun total_price'i; bossa surum kapsamlarinin
 *   (proposal_version_scopes, B43) satis toplami; para birimi surumun.
 *
 * Gizli sistem hesabi (D-120): ham sorgular kapsam disinda kaldigi icin
 * kisi adlari Personnel modeliyle (gizli hesap kapsami) cozulur; gizli hesap
 * kisi listelerine girmez, sorumlu olarak "Sistem" yazilir.
 */
final class DepartmentDashboardQueries
{
    public const WEEKS = 12;

    public const MONTHS = 12;

    /** Satir ici kucuk cizgi grafiginin hafta sayisi. */
    public const SPARK_WEEKS = 8;

    /** Teklif tablosunun en fazla satiri. */
    public const ROW_LIMIT = 500;

    /** Bekleyen teklif uyarisi: verilecek teklif bu kadar gundur verilmedi. */
    public const STALE_TO_SUBMIT_DAYS = 14;

    /** Verilen teklifin cevapsiz kaldigi gun esigi. */
    public const STALE_SUBMITTED_DAYS = 30;

    /** @var array<int, string>|null */
    private ?array $names = null;

    public function ready(): bool
    {
        return SchemaReadiness::hasBatch('B29');
    }

    /**
     * Panolarin butun verisi. $withRows: teklif satirlari (yetki varsa).
     *
     * @return array<string, mixed>
     */
    public function snapshot(bool $withRows): array
    {
        if (! $this->ready()) {
            return ['ready' => false];
        }

        return [
            'ready' => true,
            'kpi' => $this->kpis(),
            'months' => $this->months(),
            'weeks' => $this->weeks(),
            'funnel' => $this->funnel(),
            'types' => $this->types(),
            'parties' => $this->parties(),
            'people' => $this->people(),
            'notes' => $this->recentNotes(),
            'alerts' => $this->alerts(),
            'critical' => $this->critical(),
            'proposals' => $withRows ? $this->proposalRows() : [],
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Teklif satirlari                                                     */
    /* ------------------------------------------------------------------ */

    /**
     * Teklif tablosu satirlari: firma kisa adi, POTIS, proje tipleri, durum,
     * teklif tarihi, tutar, sicaklik, sorumlu, bekleme gunu ve potansiyel isin
     * son haftalardaki gorusme hareketi (kucuk cizgi grafigi).
     *
     * @return list<array<string, mixed>>
     */
    public function proposalRows(?string $status = null, ?string $search = null, ?int $ownerId = null): array
    {
        $query = $this->proposalBase()
            ->select([
                'p.id', 'p.proposal_no', 'p.title', 'p.offer_status', 'p.owner_employee_id', 'p.created_at',
                'bc.id as case_id', 'bc.title as case_title', 'bc.heat_score', 'bc.acquisition_stage', 'bc.currency_code as case_currency',
                'pa.id as party_id', 'pa.display_name', 'op.trade_name',
                'v.version_no', 'v.total_price', 'v.currency_code as version_currency', 'v.submitted_at',
            ])
            ->selectRaw($this->scopeSalesSql().' as scope_sales')
            ->orderByRaw('COALESCE(v.submitted_at, p.created_at) DESC')
            ->limit(self::ROW_LIMIT);

        if ($status !== null && OfferStatus::tryFrom($status) !== null) {
            $query->where('p.offer_status', $status);
        }

        if ($ownerId !== null) {
            $query->where('p.owner_employee_id', $ownerId);
        }

        if (filled($search)) {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $search).'%';
            $query->where(static fn (Builder $inner): Builder => $inner
                ->where('p.proposal_no', 'like', $like)
                ->orWhere('p.title', 'like', $like)
                ->orWhere('pa.display_name', 'like', $like)
                ->orWhere('op.trade_name', 'like', $like));
        }

        $rows = $query->get();
        $caseIds = $rows->pluck('case_id')->map(static fn ($id): int => (int) $id)->unique()->values()->all();
        $codes = $this->caseCodes($caseIds);
        $types = $this->caseTypes($caseIds);
        $sparks = $this->caseSparks($caseIds);
        $names = $this->names();
        $today = $this->today();
        $out = [];

        foreach ($rows as $row) {
            $caseId = (int) $row->case_id;
            $amount = $row->total_price !== null ? (float) $row->total_price : ($row->scope_sales !== null && (float) $row->scope_sales > 0 ? (float) $row->scope_sales : null);
            $submitted = $row->submitted_at !== null ? $this->local((string) $row->submitted_at) : null;
            $created = $this->local((string) $row->created_at);
            $since = $submitted ?? $created;
            $ownerId = $row->owner_employee_id !== null ? (int) $row->owner_employee_id : null;

            $out[] = [
                'id' => (int) $row->id,
                'no' => (string) $row->proposal_no,
                'title' => (string) $row->title,
                'case_id' => $caseId,
                'case_title' => (string) $row->case_title,
                'potis' => $codes[$caseId] ?? null,
                'party_id' => $row->party_id !== null ? (int) $row->party_id : null,
                'party' => self::shortName($row->trade_name, $row->display_name),
                'party_full' => (string) ($row->display_name ?? ''),
                'types' => $types[$caseId] ?? [],
                'status' => (string) $row->offer_status,
                'stage' => (string) $row->acquisition_stage,
                'version' => $row->version_no !== null ? (int) $row->version_no : null,
                'offer_date' => $submitted?->format('Y-m-d'),
                'created' => $created->format('Y-m-d'),
                'amount' => $amount,
                'currency' => (string) ($row->version_currency ?? $row->case_currency ?? ''),
                'heat' => (int) ($row->heat_score ?? 0),
                'owner_id' => $ownerId,
                'owner' => $ownerId === null ? null : ($names[$ownerId] ?? (string) __('activity.system')),
                'age' => max(0, (int) $since->diffInDays($today)),
                'spark' => $sparks[$caseId] ?? array_fill(0, self::SPARK_WEEKS, 0),
            ];
        }

        return $out;
    }

    /**
     * Firma adi tabloda kisa: kisa ad (trade_name) varsa o, yoksa adin ilk 10 harfi (D-173).
     */
    public static function shortName(?string $tradeName, ?string $displayName): string
    {
        if (filled($tradeName)) {
            return trim((string) $tradeName);
        }

        $name = trim((string) $displayName);

        return mb_strlen($name) > 10 ? rtrim(mb_substr($name, 0, 10)).'…' : $name;
    }

    /* ------------------------------------------------------------------ */
    /* Gostergeler                                                          */
    /* ------------------------------------------------------------------ */

    /**
     * @return array<string, mixed>
     */
    public function kpis(): array
    {
        $weekStart = $this->today()->startOfWeek(Carbon::MONDAY);

        // Acik potansiyel isler (kazanilabilir toplam): sonucu acik, taslak degil.
        $pipeline = DB::table('business_cases')
            ->where('is_draft', false)
            ->where('outcome', BusinessOutcome::Open->value)
            ->selectRaw('currency_code, COUNT(*) as n, COUNT(estimated_value) as valued, SUM(estimated_value) as total')
            ->groupBy('currency_code')
            ->get();

        // Teklif durumu x para birimi: sayi, tutarli sayi, toplam.
        $amount = 'COALESCE(v.total_price, '.$this->scopeSalesSql().')';
        $offers = $this->proposalBase()
            ->selectRaw('p.offer_status as status, COALESCE(v.currency_code, bc.currency_code) as currency, COUNT(*) as n, COUNT('.$amount.') as valued, SUM('.$amount.') as total')
            ->groupBy('p.offer_status', DB::raw('COALESCE(v.currency_code, bc.currency_code)'))
            ->get();

        $byStatus = [];

        foreach (OfferStatus::cases() as $status) {
            $byStatus[$status->value] = ['count' => 0, 'valued' => 0, 'sums' => []];
        }

        foreach ($offers as $row) {
            $key = (string) $row->status;

            if (! isset($byStatus[$key])) {
                continue;
            }

            $byStatus[$key]['count'] += (int) $row->n;
            $byStatus[$key]['valued'] += (int) $row->valued;

            if ($row->total !== null && (float) $row->total != 0.0) {
                $byStatus[$key]['sums'][(string) $row->currency] = ($byStatus[$key]['sums'][(string) $row->currency] ?? 0) + (float) $row->total;
            }
        }

        $outcomes = DB::table('business_cases')
            ->where('is_draft', false)
            ->selectRaw('outcome, COUNT(*) as n')
            ->groupBy('outcome')
            ->pluck('n', 'outcome')
            ->map(static fn ($n): int => (int) $n)
            ->all();

        $won = $outcomes[BusinessOutcome::Won->value] ?? 0;
        $lost = $outcomes[BusinessOutcome::Lost->value] ?? 0;

        $cases = app(BusinessCaseQueries::class);

        return [
            'pipeline' => $this->currencyBlock($pipeline),
            'offers' => $byStatus,
            'outcomes' => ['won' => $won, 'lost' => $lost, 'open' => $outcomes[BusinessOutcome::Open->value] ?? 0, 'cancelled' => $outcomes[BusinessOutcome::Cancelled->value] ?? 0],
            'win_rate' => $won + $lost > 0 ? (int) round($won * 100 / ($won + $lost)) : null,
            'week' => [
                'start' => $weekStart->format('Y-m-d'),
                'submitted' => $this->countSince('proposal_versions', 'submitted_at', $weekStart, true),
                'new_cases' => $this->countSince('business_cases', 'created_at', $weekStart),
                'notes' => $this->notesSince($weekStart),
                'won' => $this->outcomeSince(BusinessOutcome::Won, $weekStart),
                'lost' => $this->outcomeSince(BusinessOutcome::Lost, $weekStart),
            ],
            'kinds' => [
                'investor' => $cases->kindCount(false, true),
                'potential' => $cases->kindCount(true, true),
                'offer' => $cases->stageCount(BusinessCaseQueries::OFFER_STAGES, true),
                'drafts' => DB::table('business_cases')->where('is_draft', true)->count(),
            ],
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Zaman serileri                                                       */
    /* ------------------------------------------------------------------ */

    /**
     * Son 12 ay: verilen teklif, yeni potansiyel is, kazanilan, kaybedilen, gorusme notu (adet).
     *
     * @return list<array<string, mixed>>
     */
    public function months(): array
    {
        $start = $this->today()->startOfMonth()->subMonthsNoOverflow(self::MONTHS - 1);
        $fmt = "'%Y-%m'";

        $series = [
            'submitted' => $this->bucket('proposal_versions', 'submitted_at', $start, 'DATE_FORMAT('.$this->localSql('submitted_at').', '.$fmt.')', true),
            'new_cases' => $this->bucket('business_cases', 'created_at', $start, 'DATE_FORMAT('.$this->localSql('created_at').', '.$fmt.')'),
            'won' => $this->outcomeBucket(BusinessOutcome::Won, $start, 'DATE_FORMAT('.$this->localSql('outcome_at').', '.$fmt.')'),
            'lost' => $this->outcomeBucket(BusinessOutcome::Lost, $start, 'DATE_FORMAT('.$this->localSql('outcome_at').', '.$fmt.')'),
            'notes' => $this->noteBucket($start, 'DATE_FORMAT(noted_on, '.$fmt.')'),
        ];

        $out = [];

        for ($i = 0; $i < self::MONTHS; $i++) {
            $month = $start->copy()->addMonthsNoOverflow($i);
            $key = $month->format('Y-m');
            $row = ['key' => $key, 'label' => $month->copy()->locale(app()->getLocale())->translatedFormat('M y')];

            foreach ($series as $name => $values) {
                $row[$name] = $values[$key] ?? 0;
            }

            $out[] = $row;
        }

        return $out;
    }

    /**
     * Son 12 hafta (ISO hafta, pazartesi baslangic).
     *
     * @return list<array<string, mixed>>
     */
    public function weeks(): array
    {
        $start = $this->weekStart(self::WEEKS);

        $series = [
            'submitted' => $this->bucket('proposal_versions', 'submitted_at', $start, 'YEARWEEK('.$this->localSql('submitted_at').', 3)', true),
            'new_cases' => $this->bucket('business_cases', 'created_at', $start, 'YEARWEEK('.$this->localSql('created_at').', 3)'),
            'notes' => $this->noteBucket($start, 'YEARWEEK(noted_on, 3)'),
            'lost' => $this->outcomeBucket(BusinessOutcome::Lost, $start, 'YEARWEEK('.$this->localSql('outcome_at').', 3)'),
        ];

        $out = [];

        foreach ($this->weekKeys(self::WEEKS) as $key => $monday) {
            $row = ['key' => $key, 'label' => $monday->format('d.m'), 'start' => $monday->format('Y-m-d')];

            foreach ($series as $name => $values) {
                $row[$name] = $values[$key] ?? 0;
            }

            $out[] = $row;
        }

        return $out;
    }

    /* ------------------------------------------------------------------ */
    /* Huni, proje tipi, firmalar, ekip                                     */
    /* ------------------------------------------------------------------ */

    /**
     * Donusum hunisi (potansiyel is bazinda): Is Gelistirme -> Teklifte -> Verilen -> Onaylandi / Kacan.
     *
     * @return array<string, int>
     */
    public function funnel(): array
    {
        $hasProposal = 'EXISTS (SELECT 1 FROM proposals fp WHERE fp.business_case_id = bc.id AND fp.is_draft = 0)';
        $submitted = 'EXISTS (SELECT 1 FROM proposals fp LEFT JOIN proposal_versions fv ON fv.id = fp.current_version_id WHERE fp.business_case_id = bc.id AND fp.is_draft = 0 AND (fv.submitted_at IS NOT NULL OR fp.offer_status IN (?, ?)))';
        $offer = 'EXISTS (SELECT 1 FROM proposals fp WHERE fp.business_case_id = bc.id AND fp.is_draft = 0 AND fp.offer_status = ?)';

        $row = DB::table('business_cases as bc')
            ->where('bc.is_draft', false)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN '.$hasProposal.' THEN 1 ELSE 0 END) as offered')
            ->selectRaw('SUM(CASE WHEN '.$submitted.' THEN 1 ELSE 0 END) as submitted', [OfferStatus::Submitted->value, OfferStatus::Approved->value])
            ->selectRaw('SUM(CASE WHEN bc.outcome = ? OR '.$offer.' THEN 1 ELSE 0 END) as approved', [BusinessOutcome::Won->value, OfferStatus::Approved->value])
            ->selectRaw('SUM(CASE WHEN bc.outcome = ? OR '.$offer.' THEN 1 ELSE 0 END) as lost', [BusinessOutcome::Lost->value, OfferStatus::Lost->value])
            ->first();

        return [
            'development' => (int) ($row->total ?? 0),
            'offer' => (int) ($row->offered ?? 0),
            'submitted' => (int) ($row->submitted ?? 0),
            'approved' => (int) ($row->approved ?? 0),
            'lost' => (int) ($row->lost ?? 0),
        ];
    }

    /**
     * Proje tipine gore potansiyel is ve teklif sayilari (tip, getIcon / getColor tek kaynak).
     *
     * @return list<array<string, mixed>>
     */
    public function types(): array
    {
        $rows = DB::table('business_case_scopes as s')
            ->join('business_cases as bc', static fn ($join) => $join->on('bc.id', '=', 's.business_case_id')->where('bc.is_draft', '=', false))
            ->leftJoin('proposals as p', static fn ($join) => $join->on('p.business_case_id', '=', 'bc.id')->where('p.is_draft', '=', false))
            ->selectRaw('s.scope_type, COUNT(DISTINCT bc.id) as cases')
            ->selectRaw('COUNT(DISTINCT CASE WHEN bc.outcome = ? THEN bc.id END) as open_cases', [BusinessOutcome::Open->value])
            ->selectRaw('COUNT(DISTINCT p.id) as proposals')
            ->selectRaw('COUNT(DISTINCT CASE WHEN p.offer_status = ? THEN p.id END) as to_submit', [OfferStatus::ToBeSubmitted->value])
            ->selectRaw('COUNT(DISTINCT CASE WHEN p.offer_status = ? THEN p.id END) as submitted', [OfferStatus::Submitted->value])
            ->selectRaw('COUNT(DISTINCT CASE WHEN p.offer_status = ? THEN p.id END) as approved', [OfferStatus::Approved->value])
            ->selectRaw('COUNT(DISTINCT CASE WHEN p.offer_status = ? THEN p.id END) as lost', [OfferStatus::Lost->value])
            ->groupBy('s.scope_type')
            ->get()
            ->keyBy('scope_type');

        $out = [];

        // D-177: Otomasyon / Process satiri yalniz tip acikken (ScopeTypes).
        foreach (ScopeTypes::selectable() as $type) {
            $row = $rows->get($type->value);
            $out[] = [
                'key' => $type->value,
                'cases' => (int) ($row->cases ?? 0),
                'open_cases' => (int) ($row->open_cases ?? 0),
                'proposals' => (int) ($row->proposals ?? 0),
                'to_be_submitted' => (int) ($row->to_submit ?? 0),
                'submitted' => (int) ($row->submitted ?? 0),
                'approved' => (int) ($row->approved ?? 0),
                'lost' => (int) ($row->lost ?? 0),
            ];
        }

        return $out;
    }

    /**
     * En hareketli firmalar: teklif ve potansiyel is sayisi, son 90 gun gorusme, 8 haftalik hareket.
     *
     * @return list<array<string, mixed>>
     */
    public function parties(int $limit = 12): array
    {
        $rows = DB::table('parties as pa')
            ->leftJoin('organization_profiles as op', 'op.party_id', '=', 'pa.id')
            ->join('business_cases as bc', static fn ($join) => $join->on('bc.primary_party_id', '=', 'pa.id')->where('bc.is_draft', '=', false))
            ->leftJoin('proposals as p', static fn ($join) => $join->on('p.business_case_id', '=', 'bc.id')->where('p.is_draft', '=', false))
            ->whereNull('pa.merged_into_party_id')
            ->whereNull('pa.archived_at')
            ->select(['pa.id', 'pa.display_name', 'op.trade_name'])
            ->selectRaw('COUNT(DISTINCT bc.id) as cases, COUNT(DISTINCT p.id) as proposals')
            ->selectRaw('COUNT(DISTINCT CASE WHEN p.offer_status IN (?, ?) THEN p.id END) as submitted', [OfferStatus::Submitted->value, OfferStatus::Approved->value])
            ->selectRaw('COUNT(DISTINCT CASE WHEN p.offer_status = ? THEN p.id END) as lost', [OfferStatus::Lost->value])
            ->groupBy('pa.id', 'pa.display_name', 'op.trade_name')
            ->orderByDesc('proposals')
            ->orderByDesc('cases')
            ->limit($limit)
            ->get();

        $ids = $rows->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $sparks = $this->sparks('party_id', $ids);
        $recent = $ids === [] ? [] : $this->notesQuery()
            ->whereIn('party_id', $ids)
            ->where('noted_on', '>=', $this->today()->subDays(90)->format('Y-m-d'))
            ->selectRaw('party_id, COUNT(*) as n')
            ->groupBy('party_id')
            ->pluck('n', 'party_id')
            ->all();

        return $rows->map(static fn ($row): array => [
            'id' => (int) $row->id,
            'party' => self::shortName($row->trade_name, $row->display_name),
            'party_full' => (string) $row->display_name,
            'cases' => (int) $row->cases,
            'proposals' => (int) $row->proposals,
            'submitted' => (int) $row->submitted,
            'lost' => (int) $row->lost,
            'notes_90' => (int) ($recent[(int) $row->id] ?? 0),
            'spark' => $sparks[(int) $row->id] ?? array_fill(0, self::SPARK_WEEKS, 0),
        ])->values()->all();
    }

    /**
     * Ekip hareketi: kisi x hafta gorusme notu (isi haritasi), sahip oldugu
     * teklifler, acik ve kritik isler. Yalniz sayilar; rapor icerigi yok (D-147).
     *
     * @return list<array<string, mixed>>
     */
    public function people(): array
    {
        $start = $this->weekStart(self::SPARK_WEEKS);
        $keys = array_keys($this->weekKeys(self::SPARK_WEEKS));

        $notes = $this->notesQuery()
            ->whereNotNull('personnel_id')
            ->where('noted_on', '>=', $start->format('Y-m-d'))
            ->selectRaw('personnel_id, YEARWEEK(noted_on, 3) as wk, COUNT(*) as n')
            ->groupBy('personnel_id', DB::raw('YEARWEEK(noted_on, 3)'))
            ->get();

        $owned = DB::table('proposals')
            ->where('is_draft', false)
            ->whereNotNull('owner_employee_id')
            ->selectRaw('owner_employee_id as pid, COUNT(*) as n')
            ->selectRaw('SUM(CASE WHEN offer_status = ? THEN 1 ELSE 0 END) as to_submit', [OfferStatus::ToBeSubmitted->value])
            ->groupBy('owner_employee_id')
            ->get()
            ->keyBy('pid');

        $work = SchemaReadiness::hasBatch('B36')
            ? DB::table('work_items')
                ->whereIn('status', ['planned', 'in_progress', 'waiting'])
                ->selectRaw('personnel_id as pid, COUNT(*) as n, SUM(CASE WHEN is_critical = 1 THEN 1 ELSE 0 END) as critical')
                ->groupBy('personnel_id')
                ->get()
                ->keyBy('pid')
            : collect();

        $grid = [];

        foreach ($notes as $row) {
            $grid[(int) $row->personnel_id][(int) $row->wk] = (int) $row->n;
        }

        $ids = array_unique(array_merge(array_keys($grid), $owned->keys()->map(static fn ($id): int => (int) $id)->all(), $work->keys()->map(static fn ($id): int => (int) $id)->all()));
        $hidden = app(SystemAccount::class)->id();
        $people = Personnel::query()
            ->whereIn('id', $ids)
            ->when($hidden !== null, static fn ($query) => $query->whereKeyNot($hidden))
            ->with('orgUnit:id,name')
            ->get(['id', 'full_name', 'org_unit_id']);
        $out = [];

        foreach ($people as $person) {
            $id = (int) $person->getKey();
            $weeks = array_map(static fn (int $key): int => $grid[$id][$key] ?? 0, $keys);
            $out[] = [
                'id' => $id,
                'name' => (string) $person->full_name,
                'unit' => $person->orgUnit?->name,
                'weeks' => $weeks,
                'notes' => array_sum($weeks),
                'proposals' => (int) ($owned->get($id)->n ?? 0),
                'to_submit' => (int) ($owned->get($id)->to_submit ?? 0),
                'open_work' => (int) ($work->get($id)->n ?? 0),
                'critical_work' => (int) ($work->get($id)->critical ?? 0),
            ];
        }

        usort($out, static fn (array $a, array $b): int => [$b['notes'], $b['proposals']] <=> [$a['notes'], $a['proposals']]);

        return $out;
    }

    /**
     * Son gorusme notlari (potansiyel ise bagli olanlar isaretli).
     *
     * @return list<array<string, mixed>>
     */
    public function recentNotes(int $limit = 14): array
    {
        $rows = $this->notesQuery('n')
            ->leftJoin('parties as pa', 'pa.id', '=', 'n.party_id')
            ->leftJoin('organization_profiles as op', 'op.party_id', '=', 'n.party_id')
            ->leftJoin('business_cases as bc', 'bc.id', '=', 'n.business_case_id')
            ->select(['n.id', 'n.noted_on', 'n.subject', 'n.party_id', 'n.business_case_id', 'n.personnel_id', 'n.next_action', 'n.next_action_on', 'pa.display_name', 'op.trade_name', 'bc.title as case_title'])
            ->orderByDesc('n.noted_on')
            ->orderByDesc('n.id')
            ->limit($limit)
            ->get();

        $codes = $this->caseCodes($rows->pluck('business_case_id')->filter()->map(static fn ($id): int => (int) $id)->unique()->values()->all());
        $names = $this->names();
        $today = $this->today()->format('Y-m-d');

        return $rows->map(fn ($row): array => [
            'id' => (int) $row->id,
            'date' => (string) $row->noted_on,
            'subject' => mb_strimwidth((string) ($row->subject ?? ''), 0, 90, '…'),
            'party_id' => $row->party_id !== null ? (int) $row->party_id : null,
            'party' => self::shortName($row->trade_name, $row->display_name),
            'case_id' => $row->business_case_id !== null ? (int) $row->business_case_id : null,
            'case_title' => $row->case_title,
            'potis' => $row->business_case_id !== null ? ($codes[(int) $row->business_case_id] ?? null) : null,
            'person' => $row->personnel_id !== null ? ($names[(int) $row->personnel_id] ?? (string) __('activity.system')) : null,
            'next_action' => $row->next_action,
            'next_action_on' => $row->next_action_on,
            'overdue' => $row->next_action_on !== null && (string) $row->next_action_on < $today,
        ])->values()->all();
    }

    /**
     * Uyari bandi: bekleyen teklifler, cevapsiz verilen teklifler, tutari
     * girilmemis teklifler, sonucu girilmemis gecmis gorusmeler, inceleme
     * bekleyen raporlar (yalniz sayi, D-147), kritik acik isler.
     *
     * @return list<array{key: string, tone: string, count: int}>
     */
    public function alerts(): array
    {
        $today = $this->today();
        $amount = 'COALESCE(v.total_price, '.$this->scopeSalesSql().')';

        $alerts = [
            ['key' => 'stale_to_submit', 'tone' => 'warning', 'count' => $this->proposalBase()
                ->where('p.offer_status', OfferStatus::ToBeSubmitted->value)
                ->where('p.created_at', '<', $today->copy()->subDays(self::STALE_TO_SUBMIT_DAYS)->utc())
                ->count()],
            ['key' => 'stale_submitted', 'tone' => 'critical', 'count' => $this->proposalBase()
                ->where('p.offer_status', OfferStatus::Submitted->value)
                ->where('v.submitted_at', '<', $today->copy()->subDays(self::STALE_SUBMITTED_DAYS)->utc())
                ->count()],
            ['key' => 'no_amount', 'tone' => 'info', 'count' => $this->proposalBase()
                ->whereRaw($amount.' IS NULL')
                ->count()],
        ];

        if (SchemaReadiness::hasBatch('B34')) {
            $plans = DB::table('meeting_plans')
                ->where('status', 'planned')
                ->where('planned_on', '<', $today->format('Y-m-d'));

            if (SchemaReadiness::hasBatch('B44')) {
                $plans->whereNull('archived_at');
            }

            $alerts[] = ['key' => 'past_meetings', 'tone' => 'warning', 'count' => $plans->count()];
        }

        $alerts[] = ['key' => 'overdue_actions', 'tone' => 'warning', 'count' => $this->notesQuery()
            ->whereNotNull('next_action_on')
            ->where('next_action_on', '<', $today->format('Y-m-d'))
            ->count()];

        if (SchemaReadiness::hasBatch('B10A')) {
            $alerts[] = ['key' => 'reports_waiting', 'tone' => 'info', 'count' => DB::table('reports')->where('status', 'submitted')->count()];
        }

        if (SchemaReadiness::hasBatch('B07')) {
            $alerts[] = ['key' => 'approvals_waiting', 'tone' => 'info', 'count' => DB::table('approval_requests')->whereIn('status', ['pending', 'in_progress'])->count()];
        }

        if (SchemaReadiness::hasBatch('B36')) {
            $alerts[] = ['key' => 'critical_work', 'tone' => 'critical', 'count' => DB::table('work_items')
                ->whereIn('status', ['planned', 'in_progress', 'waiting'])
                ->where('is_critical', true)
                ->count()];
        }

        return $alerts;
    }

    /**
     * Son kritik kalemler: en uzun suredir cevapsiz verilen teklifler ve en eski verilecek teklifler.
     *
     * @return list<array<string, mixed>>
     */
    public function critical(int $limit = 8): array
    {
        $today = $this->today();
        $rows = $this->proposalBase()
            ->whereIn('p.offer_status', [OfferStatus::Submitted->value, OfferStatus::ToBeSubmitted->value])
            ->select(['p.id', 'p.proposal_no', 'p.title', 'p.offer_status', 'p.created_at', 'v.submitted_at', 'pa.display_name', 'op.trade_name'])
            ->orderByRaw('COALESCE(v.submitted_at, p.created_at) ASC')
            ->limit($limit)
            ->get();

        return $rows->map(function ($row) use ($today): array {
            $since = $this->local((string) ($row->submitted_at ?? $row->created_at));

            return [
                'id' => (int) $row->id,
                'no' => (string) $row->proposal_no,
                'title' => (string) $row->title,
                'party' => self::shortName($row->trade_name, $row->display_name),
                'status' => (string) $row->offer_status,
                'since' => $since->format('Y-m-d'),
                'days' => max(0, (int) $since->diffInDays($today)),
            ];
        })->values()->all();
    }

    /* ------------------------------------------------------------------ */
    /* Yardimcilar                                                          */
    /* ------------------------------------------------------------------ */

    /** Taslak olmayan teklifler + potansiyel is + firma + guncel surum. */
    private function proposalBase(): Builder
    {
        return DB::table('proposals as p')
            ->join('business_cases as bc', 'bc.id', '=', 'p.business_case_id')
            ->leftJoin('parties as pa', 'pa.id', '=', 'bc.primary_party_id')
            ->leftJoin('organization_profiles as op', 'op.party_id', '=', 'pa.id')
            ->leftJoin('proposal_versions as v', 'v.id', '=', 'p.current_version_id')
            ->where('p.is_draft', false);
    }

    /** Guncel surumun kapsam satis toplami (B43 oncesi bos). */
    private function scopeSalesSql(): string
    {
        return SchemaReadiness::hasBatch('B43')
            ? '(SELECT SUM(s.total_sales) FROM proposal_version_scopes s WHERE s.proposal_version_id = v.id)'
            : 'NULL';
    }

    /** Arsivlenmemis gorusme notlari (B44). */
    private function notesQuery(?string $alias = null): Builder
    {
        $query = DB::table($alias !== null ? 'party_meeting_notes as '.$alias : 'party_meeting_notes');

        if (SchemaReadiness::hasBatch('B44')) {
            $query->whereNull(($alias !== null ? $alias.'.' : '').'archived_at');
        }

        return $query;
    }

    /**
     * Para birimine gore toplam bloku.
     *
     * @param  iterable<object>  $rows
     * @return array{count: int, valued: int, sums: array<string, float>}
     */
    private function currencyBlock(iterable $rows): array
    {
        $block = ['count' => 0, 'valued' => 0, 'sums' => []];

        foreach ($rows as $row) {
            $block['count'] += (int) $row->n;
            $block['valued'] += (int) $row->valued;

            if ($row->total !== null && (float) $row->total != 0.0) {
                $block['sums'][(string) $row->currency_code] = (float) $row->total;
            }
        }

        return $block;
    }

    /** Kurum saatine kaydirilmis kolon ifadesi (sabit tamsayi saniye). */
    private function localSql(string $column): string
    {
        $offset = (int) Carbon::now(DisplayTime::zone())->getOffset();

        return 'DATE_ADD('.$column.', INTERVAL '.$offset.' SECOND)';
    }

    /**
     * Bir tablonun tarih kolonunu kovalara sayar. $currentVersions: yalniz tekliflerin guncel surumleri.
     *
     * @return array<string|int, int>
     */
    private function bucket(string $table, string $column, Carbon $start, string $keySql, bool $currentVersions = false): array
    {
        $query = DB::table($table)
            ->whereNotNull($column)
            ->where($column, '>=', $start->copy()->utc());

        if ($currentVersions) {
            $query->whereIn('id', DB::table('proposals')->where('is_draft', false)->whereNotNull('current_version_id')->select('current_version_id'));
        } elseif ($table === 'business_cases') {
            $query->where('is_draft', false);
        }

        return $query->selectRaw($keySql.' as k, COUNT(*) as n')
            ->groupBy(DB::raw($keySql))
            ->pluck('n', 'k')
            ->map(static fn ($n): int => (int) $n)
            ->all();
    }

    /** @return array<string|int, int> */
    private function outcomeBucket(BusinessOutcome $outcome, Carbon $start, string $keySql): array
    {
        return DB::table('business_cases')
            ->where('outcome', $outcome->value)
            ->whereNotNull('outcome_at')
            ->where('outcome_at', '>=', $start->copy()->utc())
            ->selectRaw($keySql.' as k, COUNT(*) as n')
            ->groupBy(DB::raw($keySql))
            ->pluck('n', 'k')
            ->map(static fn ($n): int => (int) $n)
            ->all();
    }

    /** @return array<string|int, int> */
    private function noteBucket(Carbon $start, string $keySql): array
    {
        return $this->notesQuery()
            ->where('noted_on', '>=', $start->format('Y-m-d'))
            ->selectRaw($keySql.' as k, COUNT(*) as n')
            ->groupBy(DB::raw($keySql))
            ->pluck('n', 'k')
            ->map(static fn ($n): int => (int) $n)
            ->all();
    }

    private function countSince(string $table, string $column, Carbon $since, bool $currentVersions = false): int
    {
        $query = DB::table($table)->where($column, '>=', $since->copy()->utc());

        if ($currentVersions) {
            $query->whereIn('id', DB::table('proposals')->where('is_draft', false)->whereNotNull('current_version_id')->select('current_version_id'));
        } elseif ($table === 'business_cases') {
            $query->where('is_draft', false);
        }

        return $query->count();
    }

    private function notesSince(Carbon $since): int
    {
        return $this->notesQuery()->where('noted_on', '>=', $since->format('Y-m-d'))->count();
    }

    private function outcomeSince(BusinessOutcome $outcome, Carbon $since): int
    {
        return DB::table('business_cases')->where('outcome', $outcome->value)->where('outcome_at', '>=', $since->copy()->utc())->count();
    }

    /**
     * Potansiyel isin kodu (POTIS; B40 oncesi eski TKLF-n).
     *
     * @param  list<int>  $caseIds
     * @return array<int, string>
     */
    private function caseCodes(array $caseIds): array
    {
        if ($caseIds === []) {
            return [];
        }

        $codes = [];

        DB::table('business_codes')
            ->whereIn('business_case_id', $caseIds)
            ->whereIn('code_kind', [BusinessCodeKind::Potential->value, BusinessCodeKind::Offer->value])
            ->orderByRaw('CASE WHEN code_kind = ? THEN 0 ELSE 1 END', [BusinessCodeKind::Potential->value])
            ->orderBy('id')
            ->get(['business_case_id', 'formatted_code'])
            ->each(function ($row) use (&$codes): void {
                $codes[(int) $row->business_case_id] ??= (string) $row->formatted_code;
            });

        return $codes;
    }

    /**
     * Potansiyel isin proje tipleri (enum sirasiyla).
     *
     * @param  list<int>  $caseIds
     * @return array<int, list<string>>
     */
    private function caseTypes(array $caseIds): array
    {
        if ($caseIds === []) {
            return [];
        }

        $order = array_flip(array_map(static fn (ProjectScopeType $type): string => $type->value, ProjectScopeType::cases()));
        $types = [];

        foreach (DB::table('business_case_scopes')->whereIn('business_case_id', $caseIds)->get(['business_case_id', 'scope_type']) as $row) {
            if (isset($order[(string) $row->scope_type])) {
                $types[(int) $row->business_case_id][] = (string) $row->scope_type;
            }
        }

        foreach ($types as $id => $list) {
            $list = array_values(array_unique($list));
            usort($list, static fn (string $a, string $b): int => $order[$a] <=> $order[$b]);
            $types[$id] = $list;
        }

        return $types;
    }

    /**
     * @param  list<int>  $caseIds
     * @return array<int, list<int>>
     */
    private function caseSparks(array $caseIds): array
    {
        return $this->sparks('business_case_id', $caseIds);
    }

    /**
     * Kayit basina son 8 haftanin gorusme notu sayilari.
     *
     * @param  list<int>  $ids
     * @return array<int, list<int>>
     */
    private function sparks(string $column, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $keys = array_keys($this->weekKeys(self::SPARK_WEEKS));
        $grid = [];

        $this->notesQuery()
            ->whereIn($column, $ids)
            ->where('noted_on', '>=', $this->weekStart(self::SPARK_WEEKS)->format('Y-m-d'))
            ->selectRaw($column.' as rid, YEARWEEK(noted_on, 3) as wk, COUNT(*) as n')
            ->groupBy($column, DB::raw('YEARWEEK(noted_on, 3)'))
            ->get()
            ->each(function ($row) use (&$grid): void {
                $grid[(int) $row->rid][(int) $row->wk] = (int) $row->n;
            });

        $out = [];

        foreach ($grid as $id => $weeks) {
            $out[$id] = array_map(static fn (int $key): int => $weeks[$key] ?? 0, $keys);
        }

        return $out;
    }

    /**
     * ISO hafta anahtari (YEARWEEK mod 3 ile ayni: 202639) => pazartesi.
     *
     * @return array<int, Carbon>
     */
    private function weekKeys(int $count): array
    {
        $start = $this->weekStart($count);
        $out = [];

        for ($i = 0; $i < $count; $i++) {
            $monday = $start->copy()->addWeeks($i);
            $out[(int) $monday->format('oW')] = $monday;
        }

        return $out;
    }

    private function weekStart(int $count): Carbon
    {
        return $this->today()->startOfWeek(Carbon::MONDAY)->subWeeks($count - 1);
    }

    private function today(): Carbon
    {
        return Carbon::now(DisplayTime::zone())->startOfDay();
    }

    private function local(string $utc): Carbon
    {
        return Carbon::parse($utc, 'UTC')->timezone(DisplayTime::zone())->startOfDay();
    }

    /**
     * Gorunur personelin adlari (gizli sistem hesabi kapsam disinda kalir, D-120).
     *
     * @return array<int, string>
     */
    private function names(): array
    {
        if ($this->names !== null) {
            return $this->names;
        }

        $hidden = app(SystemAccount::class)->id();

        return $this->names = Personnel::query()
            ->when($hidden !== null, static fn ($query) => $query->whereKeyNot($hidden))
            ->pluck('full_name', 'id')
            ->mapWithKeys(static fn ($name, $id): array => [(int) $id => (string) $name])
            ->all();
    }
}
