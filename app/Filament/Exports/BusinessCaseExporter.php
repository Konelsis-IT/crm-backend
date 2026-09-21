<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Models\Acquisition\BusinessCase;

/** Is dosyalari tablosu (BusinessCaseResource) sutunlari. */
class BusinessCaseExporter extends KonelsisExporter
{
    protected static ?string $model = BusinessCase::class;

    public static function fileLabel(): string
    {
        return __('business_case.plural');
    }

    public static function getColumns(): array
    {
        return [
            self::text('offer_code', __('business_case.fields.offer_code'))
                ->state(fn (BusinessCase $record): ?string => $record->offerCode()?->formatted_code),
            self::text('title', __('business_case.fields.title')),
            self::text('primaryParty.display_name', __('business_case.fields.primary_party')),
            self::text('acquisition_stage', __('business_case.fields.acquisition_stage')),
            self::text('outcome', __('business_case.fields.outcome')),
            self::text('criticality', __('business_case.fields.criticality')),
            self::text('offer_type', __('business_case.fields.offer_type')),
            self::text('scopes.scope_type', __('business_case.fields.scope_types')),
            self::text('owner.full_name', __('business_case.fields.owner')),
            self::decimal('estimated_value', __('business_case.fields.estimated_value')),
        ];
    }
}
