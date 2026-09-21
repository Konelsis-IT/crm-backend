<?php

declare(strict_types=1);

namespace App\Filament\Resources\MeetingPlans\Pages;

use App\Exceptions\AbstractException;
use App\Filament\Resources\MeetingPlans\MeetingPlanResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Party\MeetingPlanService;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

/** Yalniz planli gorusme duzenlenir (MeetingPlanPolicy::update). */
class EditMeetingPlan extends EditRecord
{
    protected static string $resource = MeetingPlanResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(MeetingPlanService::class)->update($record, $data);
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }

    protected function getRedirectUrl(): string
    {
        return MeetingPlanResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
