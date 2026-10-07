<?php

declare(strict_types=1);

namespace App\Query\Acquisition;

use App\Enums\Acquisition\AcquisitionStage;
use App\Models\Acquisition\BusinessCase;
use App\Models\Party\PartyMeetingNote;
use App\Services\Platform\SchemaReadiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/** Is dosyasi okuma sorgulari. */
final class BusinessCaseQueries
{
    /**
     * Liste sekmelerinin durumlari (D-162): "Teklifte" teklif hazirligindan
     * pazarliga kadar olan durumlar.
     *
     * @var list<AcquisitionStage>
     */
    public const OFFER_STAGES = [
        AcquisitionStage::OfferPreparation,
        AcquisitionStage::OfferReview,
        AcquisitionStage::Submitted,
        AcquisitionStage::Negotiation,
    ];

    /**
     * Durum sekmesi sorgusu. Taslaklar kendi sekmesinde oldugu icin durum
     * sekmelerine girmez ($excludeDrafts; taslak ozelligi kapaliysa girer).
     *
     * @param  list<AcquisitionStage>  $stages
     */
    public function withStages(Builder $query, array $stages, bool $excludeDrafts): Builder
    {
        $model = $query->getModel();

        $query->whereIn($model->qualifyColumn('acquisition_stage'), array_map(static fn (AcquisitionStage $stage): string => $stage->value, $stages));

        return $excludeDrafts ? $query->where($model->qualifyColumn('is_draft'), false) : $query;
    }

    /**
     * @param  list<AcquisitionStage>  $stages
     */
    public function stageCount(array $stages, bool $excludeDrafts): int
    {
        return $this->withStages(BusinessCase::query(), $stages, $excludeDrafts)->count();
    }

    public function total(): int
    {
        return BusinessCase::query()->count();
    }

    /**
     * Is gelistirme durumundaki kayitlar sicakliga gore (D-167, 6 Ekim 2026
     * kullanici talimati): sicaklik 0 (ya da bos) ise Yatirimci projesi,
     * 0'dan buyukse Potansiyel is.
     */
    public function withKind(Builder $query, bool $potential, bool $excludeDrafts): Builder
    {
        $query = $this->withStages($query, [AcquisitionStage::BusinessDevelopment], $excludeDrafts);
        $column = $query->getModel()->qualifyColumn('heat_score');

        return $potential
            ? $query->where($column, '>', 0)
            : $query->where(fn (Builder $inner): Builder => $inner->whereNull($column)->orWhere($column, 0));
    }

    public function kindCount(bool $potential, bool $excludeDrafts): int
    {
        return $this->withKind(BusinessCase::query(), $potential, $excludeDrafts)->count();
    }

    /**
     * Teklif olusturmadaki is dosyasi ozet karti icin kayit (22 Eylul 2026
     * kullanici karari): musteri, sorumlular ve kapsamlar (kapsam listesi
     * dokumaninin guncel dosyasiyla) tek seferde yuklenir.
     */
    public function forSummary(?int $id): ?BusinessCase
    {
        if ($id === null || $id <= 0) {
            return null;
        }

        /** @var BusinessCase|null $case */
        $case = BusinessCase::query()
            ->with(['primaryParty', 'owner', 'proposalOwner', 'project', 'scopes.scopeDocument.revisions.files.fileObject'])
            ->find($id);

        return $case;
    }

    /**
     * Potansiyel isin son gorusme tarihi ("Bu is nerede?" etiketi, D-143);
     * gorusme notu - potansiyel is baglantisi (B41) yoksa bos.
     */
    public function lastMeetingOn(int $businessCaseId): ?Carbon
    {
        if (! SchemaReadiness::hasBatch('B41')) {
            return null;
        }

        $value = PartyMeetingNote::query()->notArchived()->where('business_case_id', $businessCaseId)->max('noted_on');

        return $value === null ? null : Carbon::parse($value);
    }

    /**
     * Bir tarafin musteri oldugu potansiyel isler (gorusme notu baglantisi,
     * D-137): id => "POTIS-... · baslik", en yenisi ustte.
     *
     * @return array<int, string>
     */
    public function optionsForParty(int $partyId): array
    {
        return BusinessCase::query()
            ->with('codes')
            ->where('primary_party_id', $partyId)
            ->orderByDesc('id')
            ->get(['id', 'title', 'sequence_no'])
            ->mapWithKeys(static fn (BusinessCase $case): array => [
                (int) $case->getKey() => trim(($case->caseCode()?->formatted_code ?? '').' · '.$case->title, ' ·'),
            ])
            ->all();
    }
}
