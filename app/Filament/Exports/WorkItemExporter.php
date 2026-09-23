<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Models\Report\WorkItem;

/** Isler listesi (WorkItemResource, B36) sutunlari. */
class WorkItemExporter extends KonelsisExporter
{
    protected static ?string $model = WorkItem::class;

    public static function fileLabel(): string
    {
        return __('work_item.plural');
    }

    public static function getColumns(): array
    {
        return [
            self::dateTime('work_at', __('work_item.fields.work_at')),
            self::text('title', __('work_item.fields.title')),
            self::text('project.name', __('work_item.fields.project')),
            self::text('orgUnit.name', __('work_item.fields.org_unit')),
            self::text('category_label', __('work_item.fields.category'))
                ->state(fn (WorkItem $record): ?string => $record->categoryLabel()),
            self::text('status', __('work_item.fields.status')),
            self::text('personnel.full_name', __('work_item.fields.personnel')),
            self::boolean('is_critical', __('work_item.fields.is_critical')),
            self::text('requester_label', __('work_item.fields.requester'))
                ->state(fn (WorkItem $record): ?string => $record->requesterLabel()),
            self::text('waiting_label', __('work_item.fields.waiting'))
                ->state(fn (WorkItem $record): ?string => $record->waitingLabel()),
            self::date('due_on', __('work_item.fields.due_on')),
            self::decimal('work_hours', __('work_item.fields.work_hours')),
            self::text('source', __('work_item.fields.source')),
            self::text('parent.title', __('work_item.fields.parent')),
            self::text('note', __('work_item.fields.note')),
        ];
    }
}
