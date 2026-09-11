<?php

declare(strict_types=1);

namespace App\Models\Approval;

use App\Enums\Approval\ApprovalPolicyStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Policies\ApprovalPolicyPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Onay politikasi koku (12 SS2.1): hangi konu turu icin, hangi surum
 * gecerli. Adimlar surumdedir; yayimlanan surum degismez.
 */
#[Table('approval_policies')]
#[Fillable(['code', 'name_tr', 'name_en', 'subject_type', 'current_version_id', 'status'])]
#[UsePolicy(ApprovalPolicyPolicy::class)]
class ApprovalPolicy extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ApprovalPolicyStatus::class,
        ];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ApprovalPolicyVersion::class, 'approval_policy_id');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(ApprovalPolicyVersion::class, 'current_version_id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class, 'approval_policy_version_id');
    }

    public function localizedName(): string
    {
        return app()->getLocale() === 'en' ? (string) $this->name_en : (string) $this->name_tr;
    }
}
