<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Models\Document\Document;
use App\Models\Document\DocumentType;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;

/**
 * Doküman servisi.
 *
 * create override edilmistir: document_no arayuzde sorulmaz, secilen
 * dokuman tipinin numaralandirma onekinden uretilir (D-51'deki departman
 * kodu deseninin aynisi). is_controlled tipin varsayilanindan kopyalanir.
 *
 * createWithInitialRevision (D-75): dokuman bilgileri + belgenin asli (dosya)
 * ya da sistemde yazilan govde tek transaction'da kaydedilir; ilk revizyon
 * (01) taslak olarak acilir.
 */
final class DocumentService extends AbstractService
{
    /** Ilk revizyona ait olup dokuman tablosuna yazilmayan alanlar. */
    private const REVISION_KEYS = [
        'content_kind', 'file', 'file_temp_path', 'file_original_name', 'body_html',
        'revision_purpose', 'revision_language', 'revision_change_summary',
    ];

    /** @var list<string> */
    protected array $with = ['documentType', 'owner', 'ownerOrgUnit', 'classification', 'currentRevision'];

    protected string $orderBy = 'document_no';

    protected string $orderDirection = 'desc';

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly DocumentRevisionService $revisions,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        $documentType = DocumentType::query()->findOrFail((int) ($data['document_type_id'] ?? 0));
        $data = array_diff_key($data, array_flip(self::REVISION_KEYS));

        return parent::create([
            ...$data,
            'document_no' => $this->generateDocumentNo($documentType),
            'is_controlled' => $data['is_controlled'] ?? $documentType->is_controlled,
            'classification_id' => $data['classification_id'] ?? $documentType->default_classification_id,
            'retention_policy_id' => $data['retention_policy_id'] ?? $documentType->default_retention_policy_id,
        ]);
    }

    /**
     * Dokuman + ilk revizyon (dosya ya da sistemde yazilan govde) tek
     * transaction'da. Icerik verilmediyse yalniz dokuman kaydi acilir.
     *
     * @param  array<string, mixed>  $data
     */
    public function createWithInitialRevision(array $data): Document
    {
        return $this->transactions->run(function () use ($data): Document {
            /** @var Document $document */
            $document = $this->create($data);

            $tempPath = $data['file_temp_path'] ?? ($data['file'] ?? null);
            $body = $data['body_html'] ?? null;
            $kind = (string) ($data['content_kind'] ?? 'upload');

            $hasFile = $kind === 'upload' && filled($tempPath);
            $hasBody = $kind === 'authored' && is_string($body) && trim(strip_tags($body)) !== '';

            if ($hasFile || $hasBody) {
                $this->revisions->create([
                    'document_id' => $document->getKey(),
                    'title' => $document->title,
                    'language' => $data['revision_language'] ?? $document->default_language ?? 'tr',
                    'purpose' => $data['revision_purpose'] ?? 'for_review',
                    'change_summary' => $data['revision_change_summary'] ?? null,
                    'file_temp_path' => $hasFile ? (string) $tempPath : null,
                    'file_original_name' => $hasFile ? ($data['file_original_name'] ?? null) : null,
                    'body_html' => $hasBody ? $body : null,
                ]);
            }

            return $document;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        // document_no arayuzde duzenlenmez; ilk revizyon alanlari yalniz olusturmada vardir.
        unset($data['document_no']);
        $data = array_diff_key($data, array_flip(self::REVISION_KEYS));

        return parent::update($record, $data);
    }

    private function generateDocumentNo(DocumentType $documentType): string
    {
        $base = $documentType->numbering_prefix;
        $sequence = Document::query()->where('document_no', 'like', $base.'-%')->count() + 1;
        $documentNo = sprintf('%s-%05d', $base, $sequence);

        while (Document::query()->where('document_no', $documentNo)->exists()) {
            $sequence++;
            $documentNo = sprintf('%s-%05d', $base, $sequence);
        }

        return $documentNo;
    }
}
