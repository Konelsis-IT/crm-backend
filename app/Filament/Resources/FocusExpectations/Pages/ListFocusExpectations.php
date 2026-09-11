<?php

declare(strict_types=1);

namespace App\Filament\Resources\FocusExpectations\Pages;

use App\Exceptions\AbstractException;
use App\Filament\Resources\FocusExpectations\FocusExpectationResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Project\FocusExpectationService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class ListFocusExpectations extends ListRecords
{
    protected static string $resource = FocusExpectationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(function (array $data): Model {
                    try {
                        return app(FocusExpectationService::class)->create($data);
                    } catch (AbstractException $exception) {
                        DomainNotifications::failure($exception);

                        throw new Halt;
                    }
                }),
        ];
    }
}
