<?php

declare(strict_types=1);

namespace App\Models\Report;

use App\Models\Concerns\HasAuditColumns;
use App\Policies\WorkItemNotePolicy;
use App\Models\Personnel\Personnel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Isin tarihli notu (D-219, B57; kullanici 16 Ekim 2026: "Bir iş için birden
 * fazla notta eklenebilir"). Kaynak: mail, WhatsApp, saha raporu, toplanti...
 * Yazan `created_by_personnel_id`; aktarilan notta yazan bos, kaynak doludur.
 */
#[Table('work_item_notes')]
#[Fillable(['work_item_id', 'body', 'source', 'noted_at'])]
#[UsePolicy(WorkItemNotePolicy::class)]
class WorkItemNote extends Model
{
    use HasAuditColumns;

    public const UPDATED_AT = null;

    public function tracksUpdateAudit(): bool
    {
        return false;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'noted_at' => 'datetime',
        ];
    }

    public function workItem(): BelongsTo
    {
        return $this->belongsTo(WorkItem::class, 'work_item_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'created_by_personnel_id');
    }
}
