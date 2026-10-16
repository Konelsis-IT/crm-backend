<?php

declare(strict_types=1);

namespace App\Filament\Resources\StageNodes\Pages;

use App\Filament\Concerns\HasRecordHeading;
use App\Exceptions\StaleRecordException;
use App\Filament\Concerns\HasColoredFormActions;
use App\Filament\Resources\StageNodes\StageNodeResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Project\StageNodeService;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditStageNode extends EditRecord
{
    use HasRecordHeading;
    use HasColoredFormActions;

    protected static string $resource = StageNodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->cancelHeaderAction(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(StageNodeService::class)->update($record, $data);
        } catch (StaleRecordException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }
}
