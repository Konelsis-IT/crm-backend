<?php

declare(strict_types=1);

namespace App\Models\Report;

use App\Models\Activity\PersonnelActivity;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Yoksayilan oneri (B36, D-115): personel kendi hareketinin panoya kart
 * olarak onerilmesini istemedi. Hareket kaydi yerinde durur; bu satir
 * silinirse oneri geri gelir ("Geri al" / Yoksayilanlar listesi).
 */
#[Table('work_suggestion_dismissals')]
#[Fillable(['personnel_id', 'personnel_activity_id'])]
class WorkSuggestionDismissal extends Model
{
    use HasAuditColumns;

    public const UPDATED_AT = null;

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'personnel_id');
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(PersonnelActivity::class, 'personnel_activity_id');
    }

    public function tracksUpdateAudit(): bool
    {
        return false;
    }
}
