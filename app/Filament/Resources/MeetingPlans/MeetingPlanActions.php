<?php

declare(strict_types=1);

namespace App\Filament\Resources\MeetingPlans;

use App\Exceptions\AbstractException;
use App\Filament\Support\ActionColors;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Filament\Support\MeetingNoteComponents;
use App\Models\Party\MeetingPlan;
use App\Models\Party\PartyMeetingNote;
use App\Services\Party\MeetingPlanService;
use App\Support\DisplayTime;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Gorusme plani eylemleri (B34, D-109): liste satirinda ve ayrinti sayfasinda
 * ayni eylemler. Sonuc girilince Gorusme notlarina not yazilir; sonraki adim
 * verilirse takvime yeni planli satir olarak duser ve hatirlatmasi gider.
 * D-156: sonucu girilmis gorusmenin notu buradan duzenlenir; silme yerine arsiv.
 */
final class MeetingPlanActions
{
    public static function complete(): Action
    {
        return Action::make('complete')
            ->label(__('meeting_plan.actions.complete'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color(ActionColors::SAVE)
            ->visible(fn (MeetingPlan $record): bool => Gate::allows('complete', $record))
            ->modalHeading(__('meeting_plan.actions.complete'))
            ->modalDescription(__('meeting_plan.help.complete'))
            ->schema(fn (Schema $schema): Schema => $schema->columns(FieldGrid::MODAL_COLUMNS)->components(FieldGrid::modal([
                DatePicker::make('noted_on')
                    ->label(__('party_meeting_note.fields.noted_on'))
                    ->displayFormat('d.m.Y')
                    ->default(fn (MeetingPlan $record): string => Carbon::today(DisplayTime::zone())->min($record->planned_on ?? Carbon::today())->toDateString())
                    ->required(),
                Textarea::make('note')
                    ->label(__('party_meeting_note.fields.note'))
                    ->required()
                    ->rows(4)
                    ->columnSpan(FieldGrid::MODAL_LONG),
                // Sonraki adim tarihi yeni satirda baslar, metniyle yan yana durur (D-157).
                DatePicker::make('next_action_on')
                    ->label(__('party_meeting_note.fields.next_action_on'))
                    ->displayFormat('d.m.Y')
                    ->hintIcon(Heroicon::OutlinedInformationCircle, tooltip: __('party_meeting_note.help.next_action_reminder'))
                    ->columnStart(['md' => 1]),
                TextInput::make('next_action')
                    ->label(__('party_meeting_note.fields.next_action'))
                    ->maxLength(255),
            ])))
            ->action(fn (MeetingPlan $record, array $data, Component $livewire) => self::run(
                $livewire,
                fn (MeetingPlanService $service) => $service->complete(
                    $record,
                    (string) ($data['note'] ?? ''),
                    $data['noted_on'] ?? null,
                    $data['next_action_on'] ?? null,
                    $data['next_action'] ?? null,
                ),
                'completed',
            ));
    }

    public static function reschedule(): Action
    {
        return Action::make('reschedule')
            ->label(__('meeting_plan.actions.reschedule'))
            ->icon(Heroicon::OutlinedCalendarDays)
            ->color('gray')
            ->visible(fn (MeetingPlan $record): bool => Gate::allows('update', $record))
            ->modalHeading(__('meeting_plan.actions.reschedule'))
            ->modalDescription(__('meeting_plan.help.reschedule'))
            ->schema([
                DatePicker::make('planned_on')
                    ->label(__('meeting_plan.fields.planned_on'))
                    ->displayFormat('d.m.Y')
                    ->default(fn (MeetingPlan $record): ?string => $record->planned_on?->toDateString())
                    ->required(),
            ])
            ->action(fn (MeetingPlan $record, array $data, Component $livewire) => self::run(
                $livewire,
                fn (MeetingPlanService $service) => $service->reschedule($record, (string) $data['planned_on']),
                'rescheduled',
            ));
    }

    public static function cancel(): Action
    {
        return Action::make('cancel')
            ->label(__('meeting_plan.actions.cancel'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color(ActionColors::CANCEL)
            ->visible(fn (MeetingPlan $record): bool => Gate::allows('cancel', $record))
            ->modalHeading(__('meeting_plan.actions.cancel'))
            ->modalDescription(__('meeting_plan.help.cancel'))
            ->schema([
                Textarea::make('reason')
                    ->label(__('meeting_plan.fields.cancel_reason'))
                    ->rows(3)
                    ->maxLength(1000),
            ])
            ->action(fn (MeetingPlan $record, array $data, Component $livewire) => self::run(
                $livewire,
                fn (MeetingPlanService $service) => $service->cancel($record, $data['reason'] ?? null),
                'cancelled',
            ));
    }

    /**
     * Sonucu girilmis gorusmenin notunu duzenle (D-156: "Gorusme notlarini
     * kaydettikten sonra duzenleme ... yapilmiyor"); not formu Gorusme notlari
     * sekmesindekiyle aynidir.
     */
    public static function editNote(): Action
    {
        return MeetingNoteComponents::editNoteAction(
            static fn (Model $record): ?PartyMeetingNote => $record instanceof MeetingPlan ? $record->meetingNote : null,
        );
    }

    /**
     * Arsive al (B44, D-156): silme yerine. Sonuc notu olan gorusme notuyla
     * birlikte arsivlenir; arsivli kayit takvimde ve listede (varsayilan) gorunmez.
     */
    public static function archive(): Action
    {
        return Action::make('archive')
            ->label(__('meeting_plan.actions.archive'))
            ->icon(Heroicon::OutlinedArchiveBox)
            ->color(ActionColors::DELETE)
            ->requiresConfirmation()
            ->modalHeading(__('meeting_plan.actions.archive'))
            ->modalSubmitActionLabel(__('meeting_plan.actions.archive'))
            ->modalDescription(fn (MeetingPlan $record): string => $record->meeting_note_id !== null
                ? __('meeting_plan.help.archive_with_note')
                : __('meeting_plan.help.archive'))
            ->modalIcon(Heroicon::OutlinedArchiveBox)
            ->visible(fn (MeetingPlan $record): bool => MeetingNoteComponents::archiveEnabled() && ! $record->isArchived() && Gate::allows('archive', $record))
            ->action(fn (MeetingPlan $record, Component $livewire) => self::run(
                $livewire,
                fn (MeetingPlanService $service) => $service->archive($record),
                'archived',
            ));
    }

    /** Arsivden cikar: notuyla birlikte arsivlendiyse not da geri gelir. */
    public static function restore(): Action
    {
        return Action::make('restore')
            ->label(__('meeting_plan.actions.restore'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color(ActionColors::NEUTRAL)
            ->requiresConfirmation()
            ->modalHeading(__('meeting_plan.actions.restore'))
            ->modalDescription(__('meeting_plan.help.restore'))
            ->visible(fn (MeetingPlan $record): bool => MeetingNoteComponents::archiveEnabled() && $record->isArchived() && Gate::allows('archive', $record))
            ->action(fn (MeetingPlan $record, Component $livewire) => self::run(
                $livewire,
                fn (MeetingPlanService $service) => $service->restore($record),
                'restored',
            ));
    }

    /**
     * @param  callable(MeetingPlanService): mixed  $operation
     */
    private static function run(Component $livewire, callable $operation, string $messageKey): void
    {
        try {
            $operation(app(MeetingPlanService::class));
            DomainNotifications::success(__('meeting_plan.messages.'.$messageKey));
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            return;
        }

        // Ayrinti sayfasinda kart yeniden yuklenir; listede tablo kendiliginden yenilenir.
        if ($livewire instanceof ViewRecord) {
            $livewire->redirect(MeetingPlanResource::getUrl('view', ['record' => $livewire->getRecord()]), navigate: true);
        }
    }
}
