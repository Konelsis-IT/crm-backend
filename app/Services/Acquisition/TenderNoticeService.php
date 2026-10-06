<?php

declare(strict_types=1);

namespace App\Services\Acquisition;

use App\Exceptions\Acquisition\GuardNotSatisfiedException;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\TenderNotice;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Platform\SchemaReadiness;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Ihale ilani servisi (10 SS2.8).
 *
 * create override edilmistir: ilan ile birlikte ilk surum
 * (TenderNoticeVersionService) acilir; formdan gelen 'summary',
 * 'published_on', 'source_document_revision_id' surume yazilir.
 *
 * B43 (D-155, 5 Ekim 2026 kullanici karari): ihale potansiyel isten once gelir;
 * ilan potansiyel is secmeden acilabilir (business_case_id bos) ve taslak
 * (is_draft + draft_step) olarak kaydedilebilir. Potansiyel is ihaleden ya da
 * potansiyel is sihirbazinin ihale adimindan acilinca linkToCase() baglar.
 * Grup uygulanmadiysa eski kural gecerlidir (potansiyel is zorunlu, taslak yok).
 */
final class TenderNoticeService extends AbstractService
{
    /** @var list<string> */
    protected array $with = ['businessCase', 'source', 'issuerParty', 'currentVersion'];

    protected string $orderBy = 'captured_at';

    protected string $orderDirection = 'desc';

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly TenderNoticeVersionService $versions,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        return $this->transactions->run(function () use ($data): Model {
            $versionData = [
                'summary' => $data['summary'] ?? null,
                'published_on' => $data['published_on'] ?? null,
                'source_document_revision_id' => $data['source_document_revision_id'] ?? null,
            ];
            unset($data['summary'], $data['published_on'], $data['source_document_revision_id'], $data['current_version_id']);
            $data = self::withoutUnappliedColumns($data);

            /** @var TenderNotice $notice */
            $notice = parent::create([...$data, 'captured_at' => $data['captured_at'] ?? Carbon::now('UTC')]);

            $this->versions->create([...$versionData, 'tender_notice_id' => $notice->getKey()]);

            return $notice->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        unset($data['current_version_id'], $data['summary'], $data['published_on'], $data['source_document_revision_id'], $data['business_case_id']);

        return parent::update($record, self::withoutUnappliedColumns($data));
    }

    /**
     * Ilani potansiyel ise baglar (B43). Ilan baska bir potansiyel ise bagliysa
     * baglanti degismez; ayni ise bagliysa islem yapilmaz.
     */
    public function linkToCase(Model|int|string $record, BusinessCase $case): TenderNotice
    {
        return $this->transactions->run(function () use ($record, $case): TenderNotice {
            /** @var TenderNotice $notice */
            $notice = $this->lockForUpdate($record);
            $current = $notice->business_case_id === null ? null : (int) $notice->business_case_id;

            if ($current === (int) $case->getKey()) {
                return $notice;
            }

            if ($current !== null) {
                throw GuardNotSatisfiedException::make(['reason' => 'ihale başka bir potansiyel işe bağlı']);
            }

            $notice->forceFill(['business_case_id' => $case->getKey()])->save();
            $this->recordActivity($notice, 'linked', ['business_case_id' => $case->getKey()]);

            return $notice;
        });
    }

    /**
     * B43 uygulanmadiysa taslak kolonlari yazilmaz.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function withoutUnappliedColumns(array $data): array
    {
        if (! SchemaReadiness::hasBatch('B43')) {
            unset($data['is_draft'], $data['draft_step']);
        }

        return $data;
    }
}
