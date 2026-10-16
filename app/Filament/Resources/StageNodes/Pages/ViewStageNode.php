<?php

declare(strict_types=1);

namespace App\Filament\Resources\StageNodes\Pages;

use App\Filament\Concerns\HasRecordHeading;
use App\Filament\Resources\StageNodes\StageNodeResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewStageNode extends ViewRecord
{
    use HasRecordHeading;

    protected static string $resource = StageNodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
