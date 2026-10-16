<?php

declare(strict_types=1);

namespace App\Filament\Resources\Personnel\Pages;

use App\Filament\Concerns\HasRecordHeading;
use App\Filament\Exports\PersonnelExporter;
use App\Filament\Resources\Personnel\Actions\PersonnelStatusActions;
use App\Filament\Resources\Personnel\PersonnelResource;
use App\Filament\Support\ExportActions;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPersonnelRecord extends ViewRecord
{
    use HasRecordHeading;

    protected static string $resource = PersonnelResource::class;

    /** Kimlik karti adi basliginda yaziyor; alt baslik tekrar etmez (D-205). */
    protected function recordSubheading(): ?string
    {
        return null;
    }

    protected function getHeaderActions(): array
    {
        return [
            PersonnelStatusActions::headerGroup(),
            EditAction::make(),
            ExportActions::record(PersonnelExporter::class),
        ];
    }
}
