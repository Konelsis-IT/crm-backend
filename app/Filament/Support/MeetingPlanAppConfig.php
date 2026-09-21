<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Filament\Resources\MeetingPlans\MeetingPlanResource;
use App\Models\Party\MeetingPlan;
use App\Query\Party\MeetingPlanQueries;
use App\Query\Personnel\PersonnelQueries;
use App\Services\Party\MeetingReminderScanner;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Gorusme plani takviminin (B34, D-109) sayfayla tasinan yapilandirmasi:
 * `data-config` olarak yazilir (resources/views/filament/meetings/calendar.blade.php).
 * Sosyal medya cekirdegi (KS) ayni bicimi okur: endpoints, csrf, locale,
 * labels, timezone, today. Etiketler sosyal medya `ui` sozlugu (takvimin
 * ortak metinleri) ile `meeting_plan.ui` sozlugunun birlesimidir.
 */
final class MeetingPlanAppConfig
{
    /**
     * @return array<string, mixed>
     */
    public static function make(): array
    {
        $social = __('social_content.ui');
        $meeting = __('meeting_plan.ui');
        $month = (string) request()->query('ay', '');

        return [
            'app' => 'meetings',
            'endpoints' => ['meetings.calendar' => self::route('filament.admin.meeting-calendar.data')],
            'csrf' => csrf_token(),
            'locale' => app()->getLocale(),
            'labels' => [...(is_array($social) ? $social : []), ...(is_array($meeting) ? $meeting : [])],
            'timezone' => MeetingReminderScanner::timezone(),
            'today' => MeetingPlanQueries::today()->toDateString(),
            'month' => preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1 ? $month : null,
            'create_url' => self::safe(fn (): string => MeetingPlanResource::getUrl('create', ['tarih' => '__DATE__'])),
            'can_create' => Gate::allows('create', MeetingPlan::class),
            'can_filter' => Gate::allows('viewAny', MeetingPlan::class),
            'me' => auth()->id() !== null ? (int) auth()->id() : null,
            'personnel' => collect(app(PersonnelQueries::class)->personnelOptions())
                ->map(fn (string $name, int|string $id): array => ['value' => (string) $id, 'label' => $name])
                ->values()
                ->all(),
        ];
    }

    private static function route(string $name): ?string
    {
        return self::safe(fn (): string => route($name, absolute: false));
    }

    private static function safe(callable $resolve): ?string
    {
        try {
            return $resolve();
        } catch (Throwable) {
            return null;
        }
    }
}
