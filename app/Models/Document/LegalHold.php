<?php

declare(strict_types=1);

namespace App\Models\Document;

use App\Enums\Document\LegalHoldStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Policies\LegalHoldPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Hukuki tutma: aktifken bagli doküman/dosyalar saklama suresi
 * dolsa bile silinemez (purge kuyruguna alinamaz).
 */
#[Table('legal_holds')]
#[Fillable(['code', 'name', 'reason', 'personnel_id', 'approved_by_personnel_id', 'status', 'starts_at', 'released_at', 'release_reason'])]
#[UsePolicy(LegalHoldPolicy::class)]
class LegalHold extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LegalHoldStatus::class,
            'starts_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'personnel_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'approved_by_personnel_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(LegalHoldDocument::class, 'legal_hold_id');
    }
}
