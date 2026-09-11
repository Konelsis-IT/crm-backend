<?php

declare(strict_types=1);

namespace App\Models\Reference;

use App\Enums\Shared\ActiveStatus;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The single Konelsis organization (no SaaS tenants).
 */
#[Table('organizations')]
#[Fillable(['code', 'name_tr', 'name_en', 'default_locale', 'default_timezone', 'default_currency_code', 'status'])]
class Organization extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ActiveStatus::class,
        ];
    }

    public function defaultCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'default_currency_code', 'code');
    }

    public function legalEntities(): HasMany
    {
        return $this->hasMany(LegalEntity::class, 'organization_id');
    }
}
