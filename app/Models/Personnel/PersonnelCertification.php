<?php

declare(strict_types=1);

namespace App\Models\Personnel;

use App\Enums\Personnel\CertificationStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Policies\PersonnelCertificationPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bir personelin aldigi sertifikanin kaydi (verilis tarihi, gecerlilik).
 */
#[Table('personnel_certifications')]
#[Fillable(['personnel_id', 'certification_id', 'certificate_no', 'issued_on', 'valid_until', 'verified_by_personnel_id', 'status'])]
#[UsePolicy(PersonnelCertificationPolicy::class)]
class PersonnelCertification extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CertificationStatus::class,
            'issued_on' => 'date',
            'valid_until' => 'date',
        ];
    }

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'personnel_id');
    }

    public function certification(): BelongsTo
    {
        return $this->belongsTo(Certification::class, 'certification_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'verified_by_personnel_id');
    }
}
