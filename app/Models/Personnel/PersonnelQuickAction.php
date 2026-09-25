<?php

declare(strict_types=1);

namespace App\Models\Personnel;

use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kisinin sectigi hizli islem (B38, D-122). Islemin kendisi kodda
 * tanimlidir (QuickActionCatalog); burada yalniz kod ve sira durur.
 */
#[Table('personnel_quick_actions')]
#[Fillable(['personnel_id', 'action_code', 'sort_order'])]
class PersonnelQuickAction extends Model
{
    use HasAuditColumns;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /** Bu tabloda updated_at/row_version yok. */
    public function tracksUpdateAudit(): bool
    {
        return false;
    }

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'personnel_id');
    }
}
