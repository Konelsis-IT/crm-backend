<?php

declare(strict_types=1);

namespace App\Models\Party;

use App\Enums\Party\PartyCredentialStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Document\DocumentRevision;
use App\Models\Party\Party;
use App\Policies\PartyCertificatePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('party_certificates')]
#[Fillable([
    'party_id', 'certificate_type', 'certificate_no', 'issuer', 'issued_on', 'valid_until', 'document_revision_id',
    'status',
])]
#[UsePolicy(PartyCertificatePolicy::class)]
class PartyCertificate extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'valid_until' => 'date',
            'status' => PartyCredentialStatus::class,
        ];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }

    public function documentRevision(): BelongsTo
    {
        return $this->belongsTo(DocumentRevision::class, 'document_revision_id');
    }
}
