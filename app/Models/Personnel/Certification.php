<?php

declare(strict_types=1);

namespace App\Models\Personnel;

use App\Enums\Shared\ActiveStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Policies\CertificationPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sertifika tanimi (ornegin ISG, kaynak sertifikasi).
 */
#[Table('certifications')]
#[Fillable(['code', 'name', 'issuer', 'validity_months', 'valid_until', 'is_field_mandatory', 'status'])]
#[UsePolicy(CertificationPolicy::class)]
class Certification extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ActiveStatus::class,
            'is_field_mandatory' => 'boolean',
            'validity_months' => 'integer',
            'valid_until' => 'date',
        ];
    }

    public function personnelCertifications(): HasMany
    {
        return $this->hasMany(PersonnelCertification::class, 'certification_id');
    }
}
