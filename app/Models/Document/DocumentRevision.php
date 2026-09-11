<?php

declare(strict_types=1);

namespace App\Models\Document;

use App\Enums\Document\RevisionContentKind;
use App\Enums\Document\RevisionPurpose;
use App\Enums\Document\RevisionStatus;
use App\Models\Approval\ApprovalRequest;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Policies\DocumentRevisionPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Dokumanin bir revizyonu (versiyonu). Yayimlanmis/onaylanmis revizyon
 * uzerine yazilmaz; degisiklik yeni revizyon acar. Icerik ya yuklenen
 * dosyadir (content_kind = upload) ya da sistemde yazilan govdedir
 * (content_kind = authored, body_html) — D-75.
 */
#[Table('document_revisions')]
#[Fillable([
    'document_id', 'revision_no', 'revision_code', 'language', 'title', 'purpose', 'status', 'content_kind',
    'change_summary', 'body_html', 'content_hash', 'prepared_by_personnel_id', 'prepared_at',
    'checked_by_personnel_id', 'approved_by_personnel_id', 'approved_at', 'issued_at', 'approval_request_id',
    'superseded_by_revision_id',
])]
#[UsePolicy(DocumentRevisionPolicy::class)]
class DocumentRevision extends Model
{
    use HasAuditColumns;

    /** Onay motorundaki konu turu kodu (12 SS2.4 `subject_type`). */
    public const APPROVAL_SUBJECT_TYPE = 'document_revision';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'revision_no' => 'integer',
            'purpose' => RevisionPurpose::class,
            'status' => RevisionStatus::class,
            'content_kind' => RevisionContentKind::class,
            'prepared_at' => 'datetime',
            'approved_at' => 'datetime',
            'issued_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'prepared_by_personnel_id');
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'checked_by_personnel_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'approved_by_personnel_id');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_revision_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(DocumentRevisionFile::class, 'document_revision_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(DocumentReview::class, 'document_revision_id');
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(DocumentDistribution::class, 'document_revision_id');
    }

    public function acknowledgements(): HasMany
    {
        return $this->hasMany(DocumentAcknowledgement::class, 'document_revision_id');
    }

    /** Bu revizyona bagli son onay talebi (B07). */
    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id');
    }

    /** Bu revizyon icin acilmis tum onay talepleri (B07). */
    public function approvalRequests(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class, 'subject_id')
            ->where('subject_type', self::APPROVAL_SUBJECT_TYPE);
    }

    /** Bu revizyonun "orijinal" rolündeki dosyası, varsa. */
    public function originalFile(): ?FileObject
    {
        return $this->files()
            ->where('file_role', 'original')
            ->first()
            ?->fileObject;
    }

    /** Icerik sistemde mi yazildi? */
    public function isAuthored(): bool
    {
        return $this->content_kind === RevisionContentKind::Authored && filled($this->body_html);
    }

    /** Uzerinde calisilabilen (taslak / incelemede) revizyon mu? */
    public function isEditable(): bool
    {
        return in_array($this->status, [RevisionStatus::Draft, RevisionStatus::InReview], true);
    }
}
