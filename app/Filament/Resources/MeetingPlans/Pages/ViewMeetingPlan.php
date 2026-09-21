<?php

declare(strict_types=1);

namespace App\Filament\Resources\MeetingPlans\Pages;

use App\Exceptions\AbstractException;
use App\Filament\Exports\MeetingPlanExporter;
use App\Filament\Resources\MeetingPlans\MeetingPlanActions;
use App\Filament\Resources\MeetingPlans\MeetingPlanResource;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\ExportActions;
use App\Models\Party\MeetingPlan;
use App\Services\Party\MeetingPlanService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;

/** Gorusme ayrintisi: sonuc gir, tarih degistir, iptal, duzenle. */
class ViewMeetingPlan extends ViewRecord
{
    protected static string $resource = MeetingPlanResource::class;

    public function getTitle(): string | Htmlable
    {
        /** @var MeetingPlan $plan */
        $plan = $this->getRecord();

        return ($plan->party?->display_name ?? '-').' · '.$plan->planned_on?->format('d.m.Y');
    }

    protected function getHeaderActions(): array
    {
        return [
            MeetingPlanActions::complete(),
            MeetingPlanActions::reschedule(),
            EditAction::make()->visible(fn (MeetingPlan $record): bool => Gate::allows('update', $record)),
            MeetingPlanActions::cancel(),
            Action::make('calendar')
                ->label(__('meeting_plan.actions.calendar'))
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color('gray')
                ->url(fn (MeetingPlan $record): string => MeetingPlanResource::getUrl('index', ['ay' => $record->planned_on?->format('Y-m')])),
            DeleteAction::make()
                ->visible(fn (MeetingPlan $record): bool => Gate::allows('delete', $record))
                ->using(function (MeetingPlan $record): bool {
                    try {
                        return app(MeetingPlanService::class)->delete($record);
                    } catch (AbstractException $exception) {
                        DomainNotifications::failure($exception);

                        return false;
                    }
                })
                ->successRedirectUrl(MeetingPlanResource::getUrl('index')),
            ExportActions::record(MeetingPlanExporter::class),
        ];
    }
}
