<?php

declare(strict_types=1);

namespace App\Filament\Resources\ActivityAreas\Pages;

use App\Exceptions\AbstractException;
use App\Exceptions\CodeAlreadyInUseException;
use App\Filament\Resources\ActivityAreas\ActivityAreaResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Party\ActivityAreaService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ListActivityAreas extends ListRecords
{
    protected static string $resource = ActivityAreaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(function (array $data): Model {
                    try {
                        return app(ActivityAreaService::class)->create($data);
                    } catch (CodeAlreadyInUseException) {
                        throw ValidationException::withMessages([
                            'data.code' => __('activity_area.validation.code_taken'),
                        ]);
                    } catch (AbstractException $exception) {
                        DomainNotifications::failure($exception);

                        throw new Halt;
                    }
                }),
        ];
    }
}
