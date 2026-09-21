<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Models\Document\Document;
use App\Services\Platform\SchemaReadiness;

/** Dokumanlar tablosu (DocumentTable) sutunlari. */
class DocumentExporter extends KonelsisExporter
{
    protected static ?string $model = Document::class;

    public static function fileLabel(): string
    {
        return __('document.plural');
    }

    public static function getColumns(): array
    {
        return [
            self::text('document_no', __('document.fields.document_no')),
            self::text('title', __('document.fields.title')),
            self::text('documentType.name', __('document.fields.document_type')),
            self::text('owner.full_name', __('document.fields.owner')),
            self::text('ownerOrgUnit.name', __('document.fields.owner_org_unit')),
            ...(SchemaReadiness::hasBatch('B17') ? [self::text('project.name', __('document.fields.project'))] : []),
            self::text('currentRevision.revision_code', __('document.fields.current_revision')),
            self::boolean('is_controlled', __('document.fields.is_controlled')),
            self::text('status', __('document.fields.status')),
        ];
    }
}
