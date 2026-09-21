<?php

declare(strict_types=1);

namespace App\Query\Party;

use App\Enums\Party\MeetingPlanStatus;
use App\Filament\Resources\MeetingPlans\MeetingPlanResource;
use App\Filament\Resources\Parties\PartyResource;
use App\Models\Party\MeetingPlan;
use App\Models\Personnel\Personnel;
use App\Services\Party\MeetingReminderScanner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Gorusme plani sorgulari (B34, D-109): takvim ayi, liste sekmeleri ve
 * personel suzgeci. "Bugun" kurum saat dilimindedir.
 */
final class MeetingPlanQueries
{
    /** Takvim hucresinde en cok gosterilen ogeden fazlasi istemcide "+N" olur; sunucu hepsini doner. */
    public const MONTH_LIMIT = 1500;

    public static function today(): Carbon
    {
        return Carbon::now(MeetingReminderScanner::timezone())->startOfDay();
    }

    /**
     * Ay takvimi: gun => ogeler (sosyal medya takviminin gun bicimi: {date, items}).
     *
     * @return array{today: string, month: string, days: list<array{date: string, items: list<array<string, mixed>>}>, total: int}
     */
    public function month(string $month, ?int $personnelId = null): array
    {
        $first = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfDay();
        $last = $first->copy()->endOfMonth();
        $today = self::today();

        $query = MeetingPlan::query()
            ->with(['party', 'contact', 'personnel', 'participants'])
            ->whereBetween('planned_on', [$first->toDateString(), $last->toDateString()])
            ->orderBy('planned_on')
            ->orderBy('id')
            ->limit(self::MONTH_LIMIT);

        if ($personnelId !== null) {
            $this->involving($query, $personnelId);
        }

        $days = [];

        foreach ($query->get() as $plan) {
            $date = $plan->planned_on->toDateString();
            $days[$date] ??= ['date' => $date, 'items' => []];
            $days[$date]['items'][] = $this->item($plan, $today);
        }

        return [
            'today' => $today->toDateString(),
            'month' => $month,
            'days' => array_values($days),
            'total' => array_sum(array_map(fn (array $day): int => count($day['items']), $days)),
        ];
    }

    /** Sorumlu ya da katilan personel. */
    public function involving(Builder $query, int $personnelId): Builder
    {
        return $query->where(fn (Builder $inner): Builder => $inner
            ->where('personnel_id', $personnelId)
            ->orWhereHas('participantRows', fn (Builder $rows): Builder => $rows->where('personnel_id', $personnelId)));
    }

    /** Liste sekmeleri: upcoming | today | overdue | done | cancelled | all. */
    public function tab(Builder $query, string $tab): Builder
    {
        $today = self::today()->toDateString();

        return match ($tab) {
            'upcoming' => $query->where('status', MeetingPlanStatus::Planned->value)->whereDate('planned_on', '>=', $today),
            'today' => $query->whereDate('planned_on', $today),
            'overdue' => $query->where('status', MeetingPlanStatus::Planned->value)->whereDate('planned_on', '<', $today),
            'done' => $query->where('status', MeetingPlanStatus::Done->value),
            'cancelled' => $query->where('status', MeetingPlanStatus::Cancelled->value),
            default => $query,
        };
    }

    /**
     * Sekme rozetleri (tek sorguda degil ama sekme basina hafif sayim).
     *
     * @return array<string, int>
     */
    public function tabCounts(): array
    {
        $counts = [];

        foreach (['upcoming', 'today', 'overdue'] as $tab) {
            $counts[$tab] = $this->tab(MeetingPlan::query(), $tab)->count();
        }

        return $counts;
    }

    /** Tarih araligi suzgeci (bos uclar acik). */
    public function between(Builder $query, ?string $from, ?string $until): Builder
    {
        return $query
            ->when(filled($from), fn (Builder $inner): Builder => $inner->whereDate('planned_on', '>=', $from))
            ->when(filled($until), fn (Builder $inner): Builder => $inner->whereDate('planned_on', '<=', $until));
    }

    /**
     * Takvim cipi / gun sayfasi satiri.
     *
     * @return array<string, mixed>
     */
    private function item(MeetingPlan $plan, Carbon $today): array
    {
        $overdue = $plan->isOverdue($today);
        $status = $plan->status ?? MeetingPlanStatus::Planned;

        return [
            'id' => (int) $plan->getKey(),
            'date' => $plan->planned_on->toDateString(),
            'party' => (string) ($plan->party?->display_name ?? '-'),
            'party_url' => $this->url(fn () => $plan->party !== null ? PartyResource::getUrl('view', ['record' => $plan->party]) : null),
            'contact' => $plan->contact?->displayName(),
            'personnel' => $this->person($plan->personnel),
            'participants' => $plan->participants->map(fn (Personnel $personnel): array => $this->person($personnel))->values()->all(),
            'channel' => $plan->channel?->value,
            'channel_label' => $plan->channel?->getLabel(),
            'status' => $overdue ? 'overdue' : $status->value,
            'status_label' => $overdue ? __('meeting_plan.values.overdue') : $status->getLabel(),
            'color' => $status->paletteColor($overdue),
            'follow_up' => $plan->isFollowUp(),
            'subject' => $plan->subject,
            'url' => $this->url(fn () => MeetingPlanResource::getUrl('view', ['record' => $plan])),
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function person(?Personnel $personnel): ?array
    {
        return $personnel === null ? null : ['id' => (int) $personnel->getKey(), 'name' => (string) $personnel->full_name];
    }

    private function url(callable $resolve): ?string
    {
        try {
            $url = $resolve();

            return is_string($url) ? $url : null;
        } catch (Throwable) {
            return null;
        }
    }
}
