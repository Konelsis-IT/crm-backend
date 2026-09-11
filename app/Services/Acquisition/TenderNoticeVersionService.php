<?php

declare(strict_types=1);

namespace App\Services\Acquisition;

use App\Enums\Acquisition\TenderVersionStatus;
use App\Models\Acquisition\TenderNotice;
use App\Models\Acquisition\TenderNoticeVersion;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;

/**
 * Ihale ilani surumu servisi (10 SS2.9).
 *
 * create override edilmistir: version_no otomatik, source_hash icerikten
 * hesaplanir; ayni hash yeni surum uretmez (mevcut guncel surum doner).
 * Yeni surum acilinca onceki 'current' surum 'superseded' olur ve ilanin
 * current_version_id'si guncellenir. Surum satirlari yayimlandiktan sonra
 * duzenlenmez.
 */
final class TenderNoticeVersionService extends AbstractService
{
    protected string $model = TenderNoticeVersion::class;

    /** @var list<string> */
    protected array $with = ['capturer', 'sourceDocumentRevision'];

    protected string $orderBy = 'version_no';

    protected string $orderDirection = 'desc';

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly ActorContext $actor,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        return $this->transactions->run(function () use ($data): Model {
            /** @var TenderNotice $notice */
            $notice = TenderNotice::query()->lockForUpdate()->findOrFail((int) ($data['tender_notice_id'] ?? 0));

            $hash = hash('sha256', implode('|', [
                (string) ($data['summary'] ?? ''),
                (string) ($data['published_on'] ?? ''),
                (string) ($data['source_document_revision_id'] ?? ''),
                (string) $notice->notice_url,
            ]));

            /** @var TenderNoticeVersion|null $current */
            $current = $notice->currentVersion;

            if ($current !== null && $current->source_hash === $hash) {
                return $current;
            }

            $versionNo = (int) TenderNoticeVersion::query()->where('tender_notice_id', $notice->getKey())->max('version_no') + 1;

            /** @var TenderNoticeVersion $version */
            $version = parent::create([
                'tender_notice_id' => $notice->getKey(),
                'version_no' => $versionNo,
                'published_on' => $data['published_on'] ?? null,
                'source_hash' => $hash,
                'source_document_revision_id' => $data['source_document_revision_id'] ?? null,
                'summary' => $data['summary'] ?? null,
                'captured_by_personnel_id' => $this->actor->personnelId() ?? $notice->created_by_personnel_id,
                'status' => TenderVersionStatus::Current,
            ]);

            if ($current !== null) {
                $current->forceFill(['status' => TenderVersionStatus::Superseded])->save();
            }

            $notice->forceFill(['current_version_id' => $version->getKey()])->save();

            return $version;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        // Surum icerigi degismez; yalniz ozet notu duzeltilebilir.
        return parent::update($record, array_intersect_key($data, array_flip(['summary'])));
    }
}
