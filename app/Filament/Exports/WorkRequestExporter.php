<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Models\WorkRequest\WorkRequest;

/** Talepler tablosu (WorkRequestResource) sutunlari. */
class WorkRequestExporter extends KonelsisExporter
{
    protected static ?string $model = WorkRequest::class;

    public static function fileLabel(): string
    {
        return __('work_request.plural');
    }

    public static function getColumns(): array
    {
        return [
            self::text('request_no', __('work_request.fields.request_no')),
            self::text('title', __('work_request.fields.title')),
            self::text('priority', __('work_request.fields.priority')),
            self::text('status', __('work_request.fields.status')),
            self::text('requester_label', __('work_request.fields.from'))
                ->state(fn (WorkRequest $record): string => $record->requesterLabel()),
            self::text('target_label', __('work_request.fields.to'))
                ->state(fn (WorkRequest $record): string => $record->targetLabel()),
            self::date('due_on', __('work_request.fields.due_on')),
            self::dateTime('created_at', __('work_request.fields.created_at')),
        ];
    }
}
