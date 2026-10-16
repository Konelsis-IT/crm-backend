<?php

declare(strict_types=1);

namespace App\Models\Report;

use App\Models\Concerns\HasAuditColumns;
use App\Policies\WorkItemFilePolicy;
use App\Models\Document\FileObject;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Isin belgesi (D-219, B57; "iş model yapımıza çoklu belge eklenebilme sistemi"). */
#[Table('work_item_files')]
#[Fillable(['work_item_id', 'file_object_id', 'sort_order'])]
#[UsePolicy(WorkItemFilePolicy::class)]
class WorkItemFile extends Model
{
    use HasAuditColumns;

    public const UPDATED_AT = null;

    public function tracksUpdateAudit(): bool
    {
        return false;
    }

    public function workItem(): BelongsTo
    {
        return $this->belongsTo(WorkItem::class, 'work_item_id');
    }

    public function fileObject(): BelongsTo
    {
        return $this->belongsTo(FileObject::class, 'file_object_id');
    }
}
