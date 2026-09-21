<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Models\Party\Party;
use App\Services\Platform\SchemaReadiness;
use Filament\Actions\Exports\ExportColumn;

/** Dernekler tablosu (AssociationResource) sutunlari. */
class AssociationExporter extends KonelsisExporter
{
    protected static ?string $model = Party::class;

    public static function fileLabel(): string
    {
        return __('association.plural');
    }

    public static function getColumns(): array
    {
        return [
            self::text('display_name', __('association.fields.name')),
            ExportColumn::make('contacts_count')
                ->label(__('association.fields.contacts'))
                ->counts('contacts'),
            ...(SchemaReadiness::hasBatch('B28') ? [self::text('network_note', __('party.fields.network_note'))] : []),
            self::text('status', __('party.fields.status')),
            self::text('party_no', __('party.fields.party_no')),
        ];
    }
}
