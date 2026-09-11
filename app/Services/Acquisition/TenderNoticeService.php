<?php

declare(strict_types=1);

namespace App\Services\Acquisition;

use App\Models\Acquisition\TenderNotice;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
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
        unset($data['current_version_id'], $data['summary'], $data['published_on'], $data['source_document_revision_id']);

        return parent::update($record, $data);
    }
}
