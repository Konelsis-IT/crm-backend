<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Filament\Resources\MeetingPlans\MeetingPlanResource;
use App\Models\Party\MeetingPlan;
use App\Models\Personnel\Personnel;

/** Gorusme plani listesi (MeetingPlanResource) sutunlari. */
class MeetingPlanExporter extends KonelsisExporter
{
    protected static ?string $model = MeetingPlan::class;

    public static function fileLabel(): string
    {
        return __('meeting_plan.plural');
    }

    public static function getColumns(): array
    {
        return [
            self::date('planned_on', __('meeting_plan.fields.planned_on')),
            self::text('status', __('meeting_plan.fields.status'))
                ->state(fn (MeetingPlan $record): string => MeetingPlanResource::statusLabel($record)),
            self::text('party.display_name', __('meeting_plan.fields.party')),
            self::text('contact_name', __('meeting_plan.fields.contact'))
                ->state(fn (MeetingPlan $record): ?string => $record->contact?->displayName()),
            self::text('personnel.full_name', __('meeting_plan.fields.personnel')),
            self::text('participants_list', __('meeting_plan.fields.participants'))
                ->state(fn (MeetingPlan $record): array => $record->participants
                    ->map(fn (Personnel $personnel): string => (string) $personnel->full_name)
                    ->all()),
            self::text('channel', __('meeting_plan.fields.channel')),
            self::text('subject', __('meeting_plan.fields.subject')),
            self::text('source', __('meeting_plan.fields.source')),
        ];
    }
}
