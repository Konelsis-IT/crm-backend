<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Models\Party\Party;
use App\Models\Party\PartyActivityArea;
use App\Services\Platform\SchemaReadiness;

/**
 * Taraflar tablosu (PartyResource) sutunlari. Liste Excel'i yalniz tabloda
 * gorunen sutunlari yazar; faaliyet alanlari tabloda yok, ayrinti sayfasinin
 * Excel ve PDF'inde yer alir.
 */
class PartyExporter extends KonelsisExporter
{
    protected static ?string $model = Party::class;

    public static function fileLabel(): string
    {
        return __('party.plural');
    }

    public static function getColumns(): array
    {
        return [
            self::text('party_no', __('party.fields.party_no')),
            self::text('display_name', __('party.fields.display_name')),
            self::text('party_kind', __('party.fields.party_kind')),
            self::text('roles.role_code', __('party.fields.roles')),
            self::text('country.name_tr', __('party.fields.country')),
            self::text('status', __('party.fields.status')),
            self::dateTime('archived_at', __('party.fields.archived_at')),
            ...(SchemaReadiness::hasBatch('B28') ? [
                self::text('visit_priority', __('party.fields.visit_priority')),
                self::text('network_note', __('party.fields.network_note')),
            ] : []),
            ...(SchemaReadiness::hasBatch('B33') ? [
                self::text('activity_summary', __('party.sections.activity_areas'))
                    ->state(fn (Party $record): array => $record->activityAreas
                        ->map(fn (PartyActivityArea $row): string => $row->summary())
                        ->all()),
                self::text('origin', __('party.fields.origin')),
                self::boolean('is_competitor', __('party.fields.is_competitor')),
            ] : []),
        ];
    }
}
