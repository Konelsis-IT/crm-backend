<?php

declare(strict_types=1);

namespace App\Filament\Resources\Positions\Pages;

use App\Exceptions\StaleRecordException;
use App\Filament\Resources\Positions\PositionResource;
use App\Filament\Support\DomainNotifications;
use App\Models\Personnel\Position;
use App\Services\Personnel\PositionService;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class ViewPosition extends ViewRecord
{
    protected static string $resource = PositionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->using(function (Position $record, array $data): Model {
                    try {
                        return app(PositionService::class)->update($record, $data);
                    } catch (StaleRecordException $exception) {
                        DomainNotifications::failure($exception);

                        throw new Halt;
                    }
                }),
        ];
    }
}
