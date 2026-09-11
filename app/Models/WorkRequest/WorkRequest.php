<?php

declare(strict_types=1);

namespace App\Models\WorkRequest;

use App\Enums\WorkRequest\RequestTargetKind;
use App\Enums\WorkRequest\WorkRequestPriority;
use App\Enums\WorkRequest\WorkRequestStatus;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\Contract;
use App\Models\Acquisition\Proposal;
use App\Models\Chat\Message;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Document\Document;
use App\Models\Party\Party;
use App\Models\Personnel\OrgUnit;
use App\Models\Personnel\Personnel;
use App\Models\Project\ComponentDefinition;
use App\Models\Project\Project;
use App\Policies\WorkRequestPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Talep (B11B, D-84): bir kisi ya da birimden bir kisi ya da birime.
 * Ilgili kayit baglari istege baglidir; sohbet mesajindan acildiysa
 * `source_message_id` doludur.
 */
#[Table('work_requests')]
#[Fillable([
    'request_no', 'title', 'description', 'priority', 'status',
    'requester_personnel_id', 'requester_org_unit_id', 'target_kind', 'target_personnel_id', 'target_org_unit_id',
    'assignee_personnel_id', 'project_id', 'customer_party_id', 'component_definition_id', 'proposal_id',
    'business_case_id', 'contract_id', 'document_id', 'source_message_id', 'due_on', 'accepted_at',
    'completed_at', 'closed_at', 'closed_by_personnel_id', 'closing_note',
])]
#[UsePolicy(WorkRequestPolicy::class)]
class WorkRequest extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => WorkRequestPriority::class,
            'status' => WorkRequestStatus::class,
            'target_kind' => RequestTargetKind::class,
            'due_on' => 'date',
            'accepted_at' => 'datetime',
            'completed_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'requester_personnel_id');
    }

    public function requesterOrgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'requester_org_unit_id');
    }

    public function targetPersonnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'target_personnel_id');
    }

    public function targetOrgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'target_org_unit_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'assignee_personnel_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'closed_by_personnel_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'customer_party_id');
    }

    public function componentDefinition(): BelongsTo
    {
        return $this->belongsTo(ComponentDefinition::class, 'component_definition_id');
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class, 'proposal_id');
    }

    public function businessCase(): BelongsTo
    {
        return $this->belongsTo(BusinessCase::class, 'business_case_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function sourceMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'source_message_id');
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    public function targetsOrgUnit(): bool
    {
        return $this->target_kind === RequestTargetKind::OrgUnit;
    }

    /** "Kimden" metni: kisi, birim adina ise "Birim (kisi)". */
    public function requesterLabel(): string
    {
        $person = $this->requester?->full_name ?? '-';
        $unit = $this->requesterOrgUnit?->name;

        return $unit !== null ? sprintf('%s (%s)', $unit, $person) : $person;
    }

    /** "Kime" metni: kisi ya da birim; sorumlu atandiysa parantezde. */
    public function targetLabel(): string
    {
        $base = $this->targetsOrgUnit()
            ? ($this->targetOrgUnit?->name ?? '-')
            : ($this->targetPersonnel?->full_name ?? '-');

        $assignee = $this->assignee?->full_name;

        return $this->targetsOrgUnit() && $assignee !== null ? sprintf('%s (%s)', $base, $assignee) : $base;
    }
}
