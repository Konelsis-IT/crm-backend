<?php

declare(strict_types=1);

namespace App\Filament\Resources\MeetingPlans\Pages;

use App\Exceptions\AbstractException;
use App\Filament\Resources\MeetingPlans\MeetingPlanResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Party\MeetingPlanService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Gorusme planla. Takvimde gune tiklaninca ?tarih=YYYY-MM-DD, taraf
 * sayfasindan ?taraf=ID ile on doldurulur.
 */
class CreateMeetingPlan extends CreateRecord
{
    protected static string $resource = MeetingPlanResource::class;

    public const QUERY_DATE = 'tarih';

    public const QUERY_PARTY = 'taraf';

    protected function fillForm(): void
    {
        $this->callHook('beforeFill');

        $this->form->fill(array_filter([
            'planned_on' => $this->queryDate(),
            'party_id' => filled(request()->query(self::QUERY_PARTY)) ? (int) request()->query(self::QUERY_PARTY) : null,
        ], fn ($value): bool => $value !== null));

        $this->callHook('afterFill');
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(MeetingPlanService::class)->create($data);
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            throw ValidationException::withMessages(['data.party_id' => $exception->userMessage()]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return MeetingPlanResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    private function queryDate(): ?string
    {
        $value = (string) request()->query(self::QUERY_DATE, '');

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        return checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4)) ? $value : null;
    }
}
