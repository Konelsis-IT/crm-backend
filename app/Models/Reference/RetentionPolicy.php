<?php

declare(strict_types=1);

namespace App\Models\Reference;

use App\Enums\Reference\RetentionDisposition;
use App\Enums\Reference\RetentionTriggerKind;
use App\Enums\Shared\ActiveStatus;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

#[Table('retention_policies')]
#[Fillable(['code', 'name_tr', 'name_en', 'retention_days', 'trigger_kind', 'disposition', 'legal_basis', 'status'])]
class RetentionPolicy extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'retention_days' => 'integer',
            'trigger_kind' => RetentionTriggerKind::class,
            'disposition' => RetentionDisposition::class,
            'status' => ActiveStatus::class,
        ];
    }
}
