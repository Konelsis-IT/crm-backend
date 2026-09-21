<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Models\Report\Report;

/** Raporlar tablosu (ReportTable) sutunlari. */
class ReportExporter extends KonelsisExporter
{
    protected static ?string $model = Report::class;

    public static function fileLabel(): string
    {
        return __('report.plural');
    }

    public static function getColumns(): array
    {
        return [
            self::text('report_no', __('report.fields.report_no')),
            self::text('title', __('report.fields.title')),
            self::text('template_code', __('report.fields.template'))
                ->state(fn (Report $record): string => $record->templateName()),
            self::text('kind', __('report.fields.kind')),
            self::text('subject', __('report.fields.subject'))
                ->state(fn (Report $record): ?string => $record->subjectLabel()),
            self::text('period', __('report.fields.period'))
                ->state(fn (Report $record): ?string => $record->periodLabel()),
            self::text('author.full_name', __('report.fields.author')),
            self::text('status', __('report.fields.status')),
            self::text('reviewer.full_name', __('report.fields.reviewer')),
            self::dateTime('submitted_at', __('report.fields.submitted_at')),
            self::dateTime('created_at', __('report.fields.created_at')),
        ];
    }
}
