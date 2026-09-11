<?php

declare(strict_types=1);

namespace App\Models\Document;

use App\Enums\Document\DocumentStatus;
use App\Models\Approval\ApprovalRequest;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\OrgUnit;
use App\Models\Personnel\Personnel;
use App\Models\Project\Project;
use App\Models\Reference\RetentionPolicy;
use App\Models\Reference\SecurityClassification;
use App\Policies\DocumentPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * Dokuman kaydi. Icerik gecmisi document_revisions'tadir; current_revision_id
 * o an gecerli (yayimlanmis) revizyonu gosterir.
 */
#[Table('documents')]
#[Fillable([
    'document_no', 'title', 'document_type_id', 'is_controlled', 'owner_personnel_id', 'owner_org_unit_id', 'project_id',
    'classification_id', 'retention_policy_id', 'default_language', 'description', 'current_revision_id', 'status',
])]
#[UsePolicy(DocumentPolicy::class)]
class Document extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_controlled' => 'boolean',
            'status' => DocumentStatus::class,
        ];
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'owner_personnel_id');
    }

    public function ownerOrgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'owner_org_unit_id');
    }

    /** B17 ile eklenen proje bagi (ertelenmis FK, D-67). */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function classification(): BelongsTo
    {
        return $this->belongsTo(SecurityClassification::class, 'classification_id');
    }

    public function retentionPolicy(): BelongsTo
    {
        return $this->belongsTo(RetentionPolicy::class, 'retention_policy_id');
    }

    public function currentRevision(): BelongsTo
    {
        return $this->belongsTo(DocumentRevision::class, 'current_revision_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(DocumentRevision::class, 'document_id');
    }

    public function links(): HasMany
    {
        return $this->hasMany(DocumentLink::class, 'document_id');
    }

    /** Paylasim baglantilari (D-75). */
    public function shares(): HasMany
    {
        return $this->hasMany(DocumentShare::class, 'document_id');
    }

    /** Bu dokumanin tum revizyonlarina ait inceleme/onay kararlari. */
    public function reviews(): HasManyThrough
    {
        return $this->hasManyThrough(DocumentReview::class, DocumentRevision::class, 'document_id', 'document_revision_id');
    }

    /** Bu dokumanin tum revizyonlarina ait dagitim kayitlari. */
    public function distributions(): HasManyThrough
    {
        return $this->hasManyThrough(DocumentDistribution::class, DocumentRevision::class, 'document_id', 'document_revision_id');
    }

    /** Bu dokumanin tum revizyonlarina ait okundu/kabul teyitleri. */
    public function acknowledgements(): HasManyThrough
    {
        return $this->hasManyThrough(DocumentAcknowledgement::class, DocumentRevision::class, 'document_id', 'document_revision_id');
    }

    /** Bu dokumanin revizyonlari icin acilmis onay talepleri (B07). */
    public function approvalRequests(): HasManyThrough
    {
        return $this->hasManyThrough(ApprovalRequest::class, DocumentRevision::class, 'document_id', 'subject_id')
            ->where('approval_requests.subject_type', DocumentRevision::APPROVAL_SUBJECT_TYPE);
    }

    /** Ekranda one cikarilan revizyon: yayimlanmis olan, yoksa en son acilan. */
    public function displayRevision(): ?DocumentRevision
    {
        return $this->currentRevision ?? $this->revisions()->orderByDesc('revision_no')->first();
    }

    /** Uzerinde calisilan (taslak/incelemede) en son revizyon, varsa. */
    public function workingRevision(): ?DocumentRevision
    {
        return $this->revisions()
            ->whereIn('status', ['draft', 'in_review'])
            ->orderByDesc('revision_no')
            ->first();
    }
}
