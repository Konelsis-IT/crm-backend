<?php

declare(strict_types=1);

namespace App\Models\Acquisition;

use App\Enums\Acquisition\TenderAccessMode;
use App\Enums\Acquisition\TenderSourceType;
use App\Enums\Shared\ActiveStatus;
use App\Models\Acquisition\TenderNotice;
use App\Models\Concerns\HasAuditColumns;
use App\Policies\TenderSourcePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('tender_sources')]
#[Fillable([
    'code', 'name_tr', 'name_en', 'source_type', 'base_url', 'access_mode', 'scraping_allowed', 'terms_reference',
    'status',
])]
#[UsePolicy(TenderSourcePolicy::class)]
class TenderSource extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_type' => TenderSourceType::class,
            'access_mode' => TenderAccessMode::class,
            'scraping_allowed' => 'boolean',
            'status' => ActiveStatus::class,
        ];
    }

    public function notices(): HasMany
    {
        return $this->hasMany(TenderNotice::class, 'tender_source_id');
    }
}
