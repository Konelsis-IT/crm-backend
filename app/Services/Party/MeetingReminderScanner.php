<?php

declare(strict_types=1);

namespace App\Services\Party;

use App\Enums\Party\MeetingPlanStatus;
use App\Enums\Party\MeetingReminderStage;
use App\Filament\Resources\MeetingPlans\MeetingPlanResource;
use App\Models\Party\MeetingPlan;
use App\Models\Party\MeetingPlanReminder;
use App\Models\Personnel\Personnel;
use App\Services\Notification\PanelNotifier;
use App\Services\Platform\SchemaReadiness;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Gorusme hatirlatmasi (B34, D-109; 21 Eylul 2026 kullanici karari: "1 gun
 * once + gunun sabahi"). Zamanlayici her sabah calistirir:
 *
 *  - Yarin planli gorusme -> "Yarin gorusmeniz var"
 *  - Bugun planli gorusme -> "Bugun gorusmeniz var"
 *
 * Gorusme notlarinin tarihli "sonraki adimi" planli satir oldugu icin onun
 * hatirlatmasi da buradan gider ("Yarin / bugun sonraki adim"). Alicilar:
 * sorumlu personel ve katilanlar. Ayni plan, asama ve tarih icin bir kez
 * gonderilir (meeting_plan_reminders); tarih degisirse yeni tarih icin
 * yeniden gonderilir. "Bugun" kurum saat dilimindedir.
 */
final class MeetingReminderScanner
{
    public function __construct(private readonly PanelNotifier $notifier) {}

    /**
     * @return array{plans: int, sent: int, skipped: int}
     */
    public function scan(?Carbon $now = null): array
    {
        $result = ['plans' => 0, 'sent' => 0, 'skipped' => 0];

        if (! SchemaReadiness::hasBatch('B34')) {
            return $result;
        }

        $today = ($now ?? Carbon::now(self::timezone()))->copy()->setTimezone(self::timezone())->startOfDay();
        $tomorrow = $today->copy()->addDay();

        $plans = MeetingPlan::query()
            ->where('status', MeetingPlanStatus::Planned->value)
            ->whereIn('planned_on', [$today->toDateString(), $tomorrow->toDateString()])
            ->with(['party', 'contact', 'personnel', 'participants'])
            ->orderBy('planned_on')
            ->get();

        foreach ($plans as $plan) {
            $result['plans']++;
            $stage = $plan->planned_on->toDateString() === $today->toDateString() ? MeetingReminderStage::SameDay : MeetingReminderStage::DayBefore;

            $already = MeetingPlanReminder::query()
                ->where('meeting_plan_id', $plan->getKey())
                ->where('stage', $stage->value)
                ->whereDate('due_on', $plan->planned_on->toDateString())
                ->exists();

            if ($already) {
                $result['skipped']++;

                continue;
            }

            $recipients = $this->recipients($plan);
            $sent = $recipients->isEmpty() ? 0 : $this->notify($plan, $stage, $recipients);

            MeetingPlanReminder::query()->create([
                'meeting_plan_id' => $plan->getKey(),
                'stage' => $stage->value,
                'due_on' => $plan->planned_on->toDateString(),
                'recipient_count' => $sent,
                'sent_at' => Carbon::now('UTC'),
            ]);

            $result['sent'] += $sent;
        }

        return $result;
    }

    public static function timezone(): string
    {
        $timezone = config('konelsis.organization.default_timezone', 'Europe/Istanbul');

        return is_string($timezone) && $timezone !== '' ? $timezone : 'Europe/Istanbul';
    }

    /**
     * @return Collection<int, Personnel>
     */
    private function recipients(MeetingPlan $plan): Collection
    {
        return collect([$plan->personnel, ...$plan->participants->all()])
            ->filter(fn ($personnel): bool => $personnel instanceof Personnel && $personnel->isActive())
            ->unique(fn (Personnel $personnel) => $personnel->getKey())
            ->values();
    }

    /**
     * @param  Collection<int, Personnel>  $recipients
     */
    private function notify(MeetingPlan $plan, MeetingReminderStage $stage, Collection $recipients): int
    {
        $kind = $plan->isFollowUp() ? 'follow_up' : 'meeting';
        $party = (string) ($plan->party?->display_name ?? '-');
        $actions = [];

        try {
            $actions[] = Action::make('open')
                ->label(__('meeting_plan.actions.open'))
                ->button()
                ->url(MeetingPlanResource::getUrl('view', ['record' => $plan]))
                ->markAsRead();
        } catch (Throwable) {
            // Rota yoksa dugmesiz bildirim.
        }

        return $this->notifier->send(
            $recipients,
            __("meeting_plan.notifications.{$kind}.{$stage->value}", ['party' => $party]),
            implode(' — ', array_filter([
                $plan->subject,
                $plan->channel?->getLabel(),
                $plan->contact?->displayName(),
                $plan->planned_on->format('d.m.Y'),
            ])),
            $stage === MeetingReminderStage::SameDay ? Heroicon::OutlinedBellAlert : Heroicon::OutlinedCalendarDays,
            $stage === MeetingReminderStage::SameDay ? 'warning' : 'info',
            $actions,
        );
    }
}
