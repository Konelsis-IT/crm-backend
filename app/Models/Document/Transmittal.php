<?php

declare(strict_types=1);

namespace App\Models\Document;

use App\Enums\Document\TransmittalPurpose;
use App\Enums\Document\TransmittalStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Party\Party;
use App\Models\Personnel\Personnel;
use App\Models\Project\Project;
use App\Policies\TransmittalPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Resmi teslim tutanagi (transmittal): bir alicaya birden fazla doküman
 * revizyonunun birlikte gonderilmesi.
 */
#[Table('transmittals')]
#[Fillable([
    'transmittal_no', 'project_id', 'recipient_description', 'recipient_party_id', 'purpose', 'status',
    'issued_by_personnel_id', 'issued_at', 'cover_document_revision_id', 'external_reference',
])]
#[UsePolicy(TransmittalPolicy::class)]
class Transmittal extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => TransmittalPurpose::class,
            'status' => TransmittalStatus::class,
            'issued_at' => 'datetime',
        ];
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'issued_by_personnel_id');
    }

    /** B17 ile eklenen proje bagi (ertelenmis FK, D-67). */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /** B16 ile eklenen alici party bagi (ertelenmis FK, D-67). */
    public function recipientParty(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'recipient_party_id');
    }

    public function coverRevision(): BelongsTo
    {
        return $this->belongsTo(DocumentRevision::class, 'cover_document_revision_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransmittalItem::class, 'transmittal_id');
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(DocumentDistribution::class, 'transmittal_id');
    }
}
