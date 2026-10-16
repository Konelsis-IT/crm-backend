<?php

declare(strict_types=1);

namespace App\Filament\Resources\UnitsOfMeasure\Pages;

use App\Exceptions\AbstractException;
use App\Filament\Resources\UnitsOfMeasure\UnitOfMeasureResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Reference\UnitOfMeasureService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/** Ayarlar > Birimler listesi; "Birim ekle" pencerede (D-194). */
class ListUnitsOfMeasure extends ListRecords
{
    protected static string $resource = UnitOfMeasureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('units.actions.create'))
                ->icon(Heroicon::OutlinedPlus)
                ->modalWidth(Width::TwoExtraLarge)
                ->using(function (array $data): Model {
                    // Kod ve Ingilizce ad arayuzde girilmez (D-130, D-131); servis uretir.
                    try {
                        return app(UnitOfMeasureService::class)->create($data);
                    } catch (AbstractException $exception) {
                        DomainNotifications::failure($exception);

                        throw new Halt;
                    }
                }),
        ];
    }
}
