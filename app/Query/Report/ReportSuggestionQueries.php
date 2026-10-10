<?php

declare(strict_types=1);

namespace App\Query\Report;

use App\Enums\Report\ReportStatus;
use App\Enums\Report\ReportSubjectKind;
use App\Enums\Report\WorkItemStatus;
use App\Models\Activity\PersonnelActivity;
use App\Models\Party\PartyMeetingNote;
use App\Models\Personnel\Personnel;
use App\Models\Report\Report;
use App\Models\Report\WorkItem;
use App\Reports\ReportField;
use App\Reports\Work\WorkSuggestionCatalog;
use App\Services\Platform\SchemaReadiness;
use App\Support\DisplayTime;
use App\Support\Projects\ProjectNames;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Gunluk / haftalik rapor onerilerinin okuma sorgulari (D-167, 6 Ekim 2026
 * kullanici karari): yazarin donemdeki gorusme notlari, ilgili kayitlara
 * (proje, potansiyel is, teklif) yazdigi raporlar ve is panosunda karta
 * donmus olanlar (ayni kayit iki kez onerilmesin).
 *
 * Tarih kurali: gorusme notu `noted_on` gunune; rapor kurum saatiyle
 * yazildigi (olusturuldugu) gune duser (raporun kendi donemi baska olabilir).
 *
 * D-179 (8 Ekim 2026 kullanici istegi): rapor yazilirken daha once olusmus
 * is panosu kartlari secilir (yazarin kendi kartlari ve ondan beklenenler;
 * donemin kartlari once) ve is panosu onerileri (yazarin donemdeki
 * hareketleri; karta donmus ya da yoksayilmis olanlar haric) tek tikla
 * rapora eklenir. Kalem yalniz yazarin gorebildigi karta baglanir.
 */
final class ReportSuggestionQueries
{
    /** Bir donemde en fazla bu kadar oneri (tur basina). */
    public const LIMIT = 200;

    /** D-179: secim kutusunda bir seferde en fazla bu kadar kart. */
    public const PICK_LIMIT = 80;

    /** D-179: donemde en fazla bu kadar is panosu onerisi. */
    public const ACTIVITY_LIMIT = 30;

    public function __construct(
        private readonly WorkItemQueries $workItems,
        private readonly WorkSuggestionCatalog $catalog,
    ) {}

    /**
     * "Rapor yaz" ile ilgili kayda yazilan raporlarin konu turleri. Taraf
     * (firma) konulu rapor bu surumde yoktur (reports tablosunda taraf kolonu yok).
     *
     * @var list<ReportSubjectKind>
     */
    public const REPORT_SUBJECTS = [ReportSubjectKind::Project, ReportSubjectKind::Proposal, ReportSubjectKind::BusinessCase];

    /**
     * Yazarin (notu yazan ya da gorusmeyi yapan personel) donemdeki,
     * arsivlenmemis gorusme notlari; taraf, potansiyel is (kodlari ve
     * projesiyle), teklifler ve gorusulen kisi yuklu.
     *
     * @return Collection<int, PartyMeetingNote>
     */
    public function meetingNotes(int $personnelId, Carbon $from, Carbon $to): Collection
    {
        if (! SchemaReadiness::hasBatch('B28') || $personnelId <= 0) {
            return new Collection;
        }

        $with = ['party:id,display_name', 'contact.contact:id,display_name'];

        if (SchemaReadiness::hasBatch('B41')) {
            $with = [...$with, 'businessCase:id,title', 'businessCase.codes', 'businessCase.project:'.ProjectNames::select('business_case_id'), 'proposals:id,proposal_no,title'];
        }

        return PartyMeetingNote::query()
            ->with($with)
            ->notArchived()
            ->where(function (Builder $owner) use ($personnelId): void {
                $owner->where('personnel_id', $personnelId)
                    ->orWhere('created_by_personnel_id', $personnelId);
            })
            ->whereBetween('noted_on', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->orderBy('noted_on')
            ->orderBy('id')
            ->limit(self::LIMIT)
            ->get();
    }

    /**
     * Yazarin donemde ilgili kayitlara yazdigi raporlar: gizli, reddedilmis
     * ve calisma ozeti raporlari haric (calisma ozeti zaten konusuz oldugu
     * icin buraya girmez); haric tutulan rapor (duzenlenen raporun kendisi).
     *
     * @return Collection<int, Report>
     */
    public function writtenReports(int $personnelId, Carbon $from, Carbon $to, ?int $exceptReportId = null): Collection
    {
        if (! SchemaReadiness::hasBatch('B10A') || $personnelId <= 0) {
            return new Collection;
        }

        $zone = DisplayTime::zone();
        $fromUtc = Carbon::createFromFormat('Y-m-d', $from->format('Y-m-d'), $zone)->startOfDay()->utc();
        $toUtc = Carbon::createFromFormat('Y-m-d', $to->format('Y-m-d'), $zone)->endOfDay()->utc();

        $query = Report::query()
            ->with(['subjectProject:'.ProjectNames::select(), 'subjectProposal:id,proposal_no,title', 'subjectBusinessCase:id,title'])
            ->where('author_personnel_id', $personnelId)
            ->whereIn('subject_kind', array_map(static fn (ReportSubjectKind $kind): string => $kind->value, self::REPORT_SUBJECTS))
            ->where('is_confidential', false)
            ->where('status', '!=', ReportStatus::Rejected->value)
            ->whereBetween('created_at', [$fromUtc, $toUtc]);

        if ($exceptReportId !== null) {
            $query->whereKeyNot($exceptReportId);
        }

        return $query->orderBy('created_at')->orderBy('id')->limit(self::LIMIT)->get();
    }

    /**
     * Is panosunda karta donusturulmus gorusme notu / rapor anahtarlari
     * (`party_meeting_note:12`, `report:34`): verilen kartlarin kaynak
     * hareketlerinin konulari.
     *
     * @param  list<int>  $activityIds
     * @return list<string>
     */
    public function convertedKeys(array $activityIds): array
    {
        $activityIds = array_values(array_unique(array_filter($activityIds, static fn (int $id): bool => $id > 0)));

        if ($activityIds === []) {
            return [];
        }

        return PersonnelActivity::query()
            ->whereKey($activityIds)
            ->whereIn('subject_type', [ReportField::SOURCE_MEETING_NOTES, ReportField::SOURCE_REPORTS])
            ->get(['id', 'subject_type', 'subject_id'])
            ->map(static fn (PersonnelActivity $activity): string => $activity->subject_type.':'.(int) $activity->subject_id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * D-179: rapora secilebilecek kartlar: yazarin kendi kartlari ve ondan
     * beklenen isler. Sira: donemin kartlari (o gunlerde girilen ya da
     * tamamlanan), sonra acik kartlar, sonra digerleri; her grupta en yeni
     * once. Her kartta `report_relevance` (0 donem, 1 acik (ileri tarihli), 2 diger) vardir.
     * Arama is adi, proje adi ve bagli kayit numarasinda yapilir.
     *
     * @return Collection<int, WorkItem>
     */
    public function pickableWorkItems(int $authorId, ?Carbon $from, ?Carbon $to, string $search = '', int $limit = self::PICK_LIMIT): Collection
    {
        if (! SchemaReadiness::hasBatch('B36') || $authorId <= 0) {
            return new Collection;
        }

        $open = WorkItemStatus::openValues();
        $openMarks = implode(', ', array_fill(0, count($open), '?'));

        if ($from !== null && $to !== null) {
            $fromYmd = $from->format('Y-m-d');
            $toYmd = $to->format('Y-m-d');
            // Donem: periodCards ile ayni kural (o gunlerde girilen ya da
            // tamamlanan, onceki gunlerden devreden acik kart).
            $relevance = 'CASE WHEN work_items.work_on BETWEEN ? AND ? OR work_items.done_on BETWEEN ? AND ?'
                .' OR (work_items.status IN ('.$openMarks.') AND work_items.work_on < ?) THEN 0'
                .' WHEN work_items.status IN ('.$openMarks.') THEN 1 ELSE 2 END';
            $bindings = [$fromYmd, $toYmd, $fromYmd, $toYmd, ...$open, $fromYmd, ...$open];
        } else {
            $relevance = 'CASE WHEN work_items.status IN ('.$openMarks.') THEN 1 ELSE 2 END';
            $bindings = $open;
        }

        $query = WorkItem::query()
            ->select('work_items.*')
            ->selectRaw($relevance.' as report_relevance', $bindings)
            ->with(['project:'.ProjectNames::select()])
            ->where(function (Builder $owner) use ($authorId): void {
                $owner->where('work_items.personnel_id', $authorId)
                    ->orWhere('work_items.waiting_personnel_id', $authorId);
            });

        if (trim($search) !== '') {
            $this->workItems->applySearch($query, $search);
        }

        return $query
            ->orderBy('report_relevance')
            ->orderByDesc('work_items.work_at')
            ->orderByDesc('work_items.id')
            ->limit(max(1, $limit))
            ->get();
    }

    /**
     * D-179: secilen tek kart, rapor kalemi icin butun iliskileriyle; yalniz
     * yazarin kendi karti ya da ondan beklenen is.
     */
    public function pickableWorkItem(int $authorId, int $id): ?WorkItem
    {
        if (! SchemaReadiness::hasBatch('B36') || $authorId <= 0 || $id <= 0) {
            return null;
        }

        $item = $this->workItems->newQuery()
            ->whereKey($id)
            ->where(function (Builder $owner) use ($authorId): void {
                $owner->where('work_items.personnel_id', $authorId)
                    ->orWhere('work_items.waiting_personnel_id', $authorId);
            })
            ->first();

        return $item instanceof WorkItem ? $item : null;
    }

    /**
     * D-179: verilen kart kimliklerinden yazarin gorebildikleri (rapor kalemi
     * yalniz bunlara baglanir; is panosu gorunurluk kurali).
     *
     * @param  list<int>  $ids
     * @return list<int>
     */
    public function visibleWorkItemIds(Personnel $viewer, array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));

        if ($ids === [] || ! SchemaReadiness::hasBatch('B36')) {
            return [];
        }

        return $this->workItems->applyVisible(WorkItem::query(), $viewer)
            ->whereIn('work_items.id', $ids)
            ->pluck('work_items.id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * D-179: is panosu onerileri rapor donemi icin: yazarin donemdeki
     * desteklenen hareketleri; karta donmus ya da yazarin yoksaydigi hareket
     * haric. Ayni kayit ve islem icin en yeni hareket.
     *
     * @return Collection<int, PersonnelActivity>
     */
    public function workSuggestionActivities(int $authorId, Carbon $from, Carbon $to): Collection
    {
        if (! SchemaReadiness::hasBatch('B36') || $authorId <= 0) {
            return new Collection;
        }

        $zone = DisplayTime::zone();
        $fromUtc = Carbon::createFromFormat('Y-m-d', $from->format('Y-m-d'), $zone)->startOfDay()->utc();
        $toUtc = Carbon::createFromFormat('Y-m-d', $to->format('Y-m-d'), $zone)->endOfDay()->utc();

        $activities = PersonnelActivity::query()
            ->where('personnel_id', $authorId)
            ->whereIn('action_code', $this->catalog->actionCodes())
            ->whereBetween('occurred_at', [$fromUtc, $toUtc])
            ->whereNotExists(function ($sub): void {
                $sub->select(DB::raw(1))
                    ->from('work_items')
                    ->whereColumn('work_items.source_activity_id', 'personnel_activities.id');
            })
            ->whereNotExists(function ($sub) use ($authorId): void {
                $sub->select(DB::raw(1))
                    ->from('work_suggestion_dismissals')
                    ->whereColumn('work_suggestion_dismissals.personnel_activity_id', 'personnel_activities.id')
                    ->where('work_suggestion_dismissals.personnel_id', $authorId);
            })
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(self::ACTIVITY_LIMIT * 3)
            ->get();

        return $activities
            ->unique(fn (PersonnelActivity $activity): string => $activity->action_code.'|'.$activity->subject_type.'|'.$activity->subject_id)
            ->take(self::ACTIVITY_LIMIT)
            ->values();
    }
}
