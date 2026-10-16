<?php

declare(strict_types=1);

namespace App\Filament\Resources\Parties\Pages;

use App\Filament\Concerns\HasRecordHeading;
use App\Filament\Exports\KonelsisExporter;
use App\Filament\Exports\PartyExporter;
use App\Filament\Resources\Parties\PartyResource;
use App\Filament\Support\ExportActions;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewParty extends ViewRecord
{
    use HasRecordHeading;

    protected static string $resource = PartyResource::class;

    /** Kimlik karti adi basliginda yaziyor; alt baslik tekrar etmez (D-205). */
    protected function recordSubheading(): ?string
    {
        return null;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            PartyResource::archiveAction(),
            PartyResource::restoreAction(),
            ExportActions::record(static::exporter()),
        ];
    }

    /** @return class-string<KonelsisExporter> */
    protected static function exporter(): string
    {
        return PartyExporter::class;
    }
}
