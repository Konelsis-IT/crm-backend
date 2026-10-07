<?php

declare(strict_types=1);

namespace App\Query\Report;

use App\Enums\Report\ReportStatus;
use App\Enums\Report\ReportSubjectKind;
use App\Models\Activity\PersonnelActivity;
use App\Models\Party\PartyMeetingNote;
use App\Models\Report\Report;
use App\Reports\ReportField;
use App\Services\Platform\SchemaReadiness;
use App\Support\DisplayTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Gunluk / haftalik rapor onerilerinin okuma sorgulari (D-167, 6 Ekim 2026
 * kullanici karari): yazarin donemdeki gorusme notlari, ilgili kayitlara
 * (proje, potansiyel is, teklif) yazdigi raporlar ve is panosunda karta
 * donmus olanlar (ayni kayit iki kez onerilmesin).
 *
 * Tarih kurali: gorusme notu `noted_on` gunune; rapor kurum saatiyle
 * yazildigi (olusturuldugu) gune duser (raporun kendi donemi baska olabilir).
 */
final class ReportSuggestionQueries
{
    /** Bir donemde en fazla bu kadar oneri (tur basina). */
    public const LIMIT = 200;

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
            $with = [...$with, 'businessCase:id,title', 'businessCase.codes', 'businessCase.project:id,business_case_id,name', 'proposals:id,proposal_no,title'];
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
            ->with(['subjectProject:id,name', 'subjectProposal:id,proposal_no,title', 'subjectBusinessCase:id,title'])
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
}
