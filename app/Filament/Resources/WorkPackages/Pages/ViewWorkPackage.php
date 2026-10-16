<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkPackages\Pages;

use App\Filament\Concerns\HasRecordHeading;
use App\Filament\Resources\WorkPackages\WorkPackageResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWorkPackage extends ViewRecord
{
    use HasRecordHeading;

    protected static string $resource = WorkPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
