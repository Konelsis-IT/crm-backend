<?php

declare(strict_types=1);

namespace App\Filament\Resources\MeetingPlans\Pages;

use App\Filament\Exports\MeetingPlanExporter;
use App\Filament\Resources\MeetingPlans\MeetingPlanActions;
use App\Filament\Resources\MeetingPlans\MeetingPlanResource;
use App\Filament\Support\ExportActions;
use App\Models\Party\MeetingPlan;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;

/**
 * Gorusme ayrintisi: sonuc gir, tarih degistir, gerceklesmedi, duzenle.
 * D-156 (5 Ekim 2026): sonucu girilmis gorusmenin notu buradan duzenlenir;
 * silme yoktur, arsive alinir (sonuc notu varsa notuyla birlikte).
 */
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
            EditAction::make()->visible(fn (MeetingPlan $record): bool => ! $record->isArchived() && Gate::allows('update', $record)),
            MeetingPlanActions::cancel(),
            MeetingPlanActions::editNote(),
            Action::make('calendar')
                ->label(__('meeting_plan.actions.calendar'))
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color('gray')
                ->url(fn (MeetingPlan $record): string => MeetingPlanResource::getUrl('index', ['ay' => $record->planned_on?->format('Y-m')])),
            MeetingPlanActions::archive(),
            MeetingPlanActions::restore(),
            ExportActions::record(MeetingPlanExporter::class),
        ];
    }
}
