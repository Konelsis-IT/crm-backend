<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Enums\Document\TemplateVersionStatus;
use App\Models\Document\DocumentTemplateVersion;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Sablon surumu servisi.
 *
 * create override edilmistir: version_no her (sablon, dil) cifti icin
 * otomatik uretilir (document_revisions'taki desenin aynisi).
 *
 * create/update icinde durum "published" secildiginde yayimlayan/yayim
 * tarihi otomatik doldurulur.
 */
final class DocumentTemplateVersionService extends AbstractService
{
    /** @var list<string> */
    protected array $with = ['template', 'publisher'];

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
        $templateId = (int) ($data['document_template_id'] ?? 0);
        $locale = (string) ($data['locale'] ?? '');
        $versionNo = DocumentTemplateVersion::query()
            ->where('document_template_id', $templateId)
            ->where('locale', $locale)
            ->max('version_no') + 1;

        return parent::create($this->stampIfPublished([
            ...$data,
            'document_template_id' => $templateId,
            'version_no' => $versionNo,
            'content_hash' => hash('sha256', ($data['view_key'] ?? '').$locale.$versionNo.now()->toIso8601String()),
        ]));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        unset($data['document_template_id'], $data['version_no']);

        return parent::update($record, $this->stampIfPublished($data));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function stampIfPublished(array $data): array
    {
        if (($data['status'] ?? null) === TemplateVersionStatus::Published->value) {
            $data['published_by_personnel_id'] = $this->actor->personnelId();
            $data['published_at'] = Carbon::now('UTC');
        }

        return $data;
    }
}
