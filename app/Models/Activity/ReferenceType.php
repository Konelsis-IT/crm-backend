<?php

declare(strict_types=1);

namespace App\Models\Activity;

use App\Enums\Activity\RegistryStatus;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Hangi kayit turlerinin hareket/giden kutusu gibi ortak alanlarda
 * gosterilebilecegini tanimlayan liste. Listede olmayan tur reddedilir.
 */
#[Table('reference_types')]
#[Fillable(['target_type', 'table_name', 'label_key', 'owning_domain', 'status'])]
class ReferenceType extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RegistryStatus::class,
        ];
    }

    public function usages(): HasMany
    {
        return $this->hasMany(ReferenceTypeUsage::class, 'reference_type_id');
    }
}
