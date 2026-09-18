<?php

declare(strict_types=1);

namespace App\Services\SocialMedia;

use App\Enums\SocialMedia\SocialContentStatus;
use App\Enums\SocialMedia\SocialReminderStage;
use App\Filament\Resources\SocialContents\SocialContentResource;
use App\Filament\Support\SocialAppConfig;
use App\Models\Personnel\Personnel;
use App\Models\SocialMedia\SocialContent;
use App\Query\SocialMedia\SocialContentQueries;
use App\Query\SocialMedia\SocialResponsibilityQueries;
use App\Services\Notification\PanelNotifier;
use App\Services\Platform\SchemaReadiness;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Throwable;

/**
 * Paylasim gunu yaklasan sosyal medya iceriklerinin zil hatirlatmasi (B31,
 * D-106; kullanici istegi: "yaklasti, bugun sunlar paylasilacak gibi hazirlayan
 * personele uyari verilecek ... normalde surekli bildirim gitmeyecek").
 *
 * - Aday: durumu bekliyor / revize edilsin / onaylandi, paylasilmamis ve plan
 *   tarihi olan icerik (SocialContentQueries::reminderCandidates()).
 * - Asama: gecikti (plan tarihi gecmis), bugun, yarin, yaklasiyor
 *   (2..approaching_days gun). "Bugun" yalniz SocialClock'tan gelir.
 * - Tekrar yok: `reminder_key = "<plan tarihi>:<asama>"`. Anahtar ayniysa
 *   icerik atlanir; yani her asama icin BIR kez, gecikme icin de bir kez
 *   hatirlatilir. Plan tarihi degisince servis anahtari sifirlar.
 * - Alicilar: icerigi hazirlayan + sorumlu gorevlerdeki personel (tekil).
 *   Her aliciya calistirma basina TEK ozet bildirim gider; en fazla 5 baslik
 *   listelenir. Bugun / yarin paylasilacak ama henuz onaylanmamis icerik
 *   satirina "onay bekliyor" eklenir.
 * - Bu sinif veritabanina yazmaz: anahtar SocialContentService::markReminded()
 *   ile (satir surumu artmadan) ve yalniz en az bir aliciya ulasildiysa yazilir;
 *   zil kapaliyken ya da alici yokken icerik sonraki calistirmada yeniden denenir.
 */
final class SocialReminderScanner
{
    /** Ozet bildirimde listelenen en fazla baslik. */
    public const MAX_TITLES = 5;

    /** Son calisma ani (ISO-8601); bootstrap `reminders.last_run_at` bunu okur. */
    public const CACHE_LAST_RUN = 'social.remind.last_run';

    /** Ozet ve liste sirasi: once bugun, sonra yarin, yaklasan, geciken. */
    private const STAGE_ORDER = [
        SocialReminderStage::Today,
        SocialReminderStage::Tomorrow,
        SocialReminderStage::Approaching,
        SocialReminderStage::Missed,
    ];

    public function __construct(
        private readonly SocialContentQueries $contents,
        private readonly SocialResponsibilityQueries $responsibility,
        private readonly SocialContentService $service,
        private readonly PanelNotifier $notifier,
    ) {}

    /**
     * @param  CarbonImmutable|null  $now  Yalniz elle / denetim amacli calistirma icin;
     *                                     bos birakilinca "bugun" SocialClock::today()'dir.
     * @return array{candidates: int, due: int, recipients: int, notified: int, marked: int}
     */
    public function scan(?CarbonImmutable $now = null): array
    {
        $result = ['candidates' => 0, 'due' => 0, 'recipients' => 0, 'notified' => 0, 'marked' => 0];

        if (! SchemaReadiness::hasBatch('B31')) {
            return $result;
        }

        $today = $now === null
            ? SocialClock::today()
            : SocialClock::date($now->setTimezone(SocialClock::timezone()));

        $candidates = $this->contents->reminderCandidates();
        $result['candidates'] = $candidates->count();

        /** @var array<int, array{content: SocialContent, stage: SocialReminderStage, key: string}> $due */
        $due = [];

        foreach ($candidates as $content) {
            if (! $content instanceof SocialContent || $content->planned_on === null) {
                continue;
            }

            $stage = $this->stageOn($content, $today);

            if ($stage === null) {
                continue;
            }

            $key = $content->planned_on->format('Y-m-d').':'.$stage->value;

            // Bu asama icin daha once hatirlatildi.
            if ((string) $content->getAttribute('reminder_key') === $key) {
                continue;
            }

            $due[(int) $content->getKey()] = ['content' => $content, 'stage' => $stage, 'key' => $key];
        }

        $result['due'] = count($due);

        if ($due === [] || ! $this->notifier->enabled()) {
            return $result;
        }

        $digests = $this->digests($due);
        $result['recipients'] = count($digests);
        $actions = $this->actions();
        $delivered = [];

        foreach ($digests as $digest) {
            $sent = $this->notifier->send(
                [$digest['personnel']],
                $this->title($digest['items']),
                $this->body($digest['items']),
                Heroicon::OutlinedCalendarDays,
                $this->color($digest['items']),
                $actions,
            );

            if ($sent < 1) {
                continue;
            }

            $result['notified'] += $sent;

            foreach ($digest['items'] as $item) {
                $delivered[(int) $item['content']->getKey()] = true;
            }
        }

        foreach (array_keys($delivered) as $contentId) {
            $this->service->markReminded($due[$contentId]['content'], $due[$contentId]['key']);
            $result['marked']++;
        }

        return $result;
    }

    /** Icerigin verilen gune gore asamasi (takvim gunu farki; saat dilimi kaymasi olmaz). */
    private function stageOn(SocialContent $content, CarbonImmutable $today): ?SocialReminderStage
    {
        $days = (int) round($today->diffInDays(SocialClock::date($content->planned_on), false));

        return SocialReminderStage::forDays($days, SocialClock::approachingDays());
    }

    /**
     * Alici basina ozet: icerigi hazirlayan + sorumlu personel, tekil.
     *
     * @param  array<int, array{content: SocialContent, stage: SocialReminderStage, key: string}>  $due
     * @return array<int, array{personnel: Personnel, items: list<array{content: SocialContent, stage: SocialReminderStage, key: string}>}>
     */
    private function digests(array $due): array
    {
        $responsibles = $this->responsibility->responsiblePeople()
            ->filter(fn (mixed $person): bool => $person instanceof Personnel && $person->isReachable())
            ->values();

        $digests = [];

        foreach ($due as $item) {
            $recipients = [];
            $creator = $item['content']->createdBy;

            if ($creator instanceof Personnel && $creator->isReachable()) {
                $recipients[(int) $creator->getKey()] = $creator;
            }

            foreach ($responsibles as $person) {
                $recipients[(int) $person->getKey()] ??= $person;
            }

            foreach ($recipients as $personnelId => $personnel) {
                $digests[$personnelId] ??= ['personnel' => $personnel, 'items' => []];
                $digests[$personnelId]['items'][] = $item;
            }
        }

        foreach ($digests as $personnelId => $digest) {
            $digests[$personnelId]['items'] = $this->sorted($digest['items']);
        }

        return $digests;
    }

    /**
     * Asama sirasi (bugun, yarin, yaklasan, geciken), sonra plan tarihi ve saati.
     *
     * @param  list<array{content: SocialContent, stage: SocialReminderStage, key: string}>  $items
     * @return list<array{content: SocialContent, stage: SocialReminderStage, key: string}>
     */
    private function sorted(array $items): array
    {
        usort($items, function (array $a, array $b): int {
            return [array_search($a['stage'], self::STAGE_ORDER, true), $this->sortKey($a['content'])]
                <=> [array_search($b['stage'], self::STAGE_ORDER, true), $this->sortKey($b['content'])];
        });

        return $items;
    }

    private function sortKey(SocialContent $content): string
    {
        return $content->planned_on?->format('Y-m-d').' '.($content->planned_time ?: '99:99');
    }

    /**
     * "Sosyal medya: bugun 2, yarin 1, yaklasan 3 icerik" - yalniz sifirdan
     * buyuk asamalar yazilir.
     *
     * @param  list<array{content: SocialContent, stage: SocialReminderStage, key: string}>  $items
     */
    private function title(array $items): string
    {
        $counts = $this->counts($items);
        $parts = [];

        foreach (self::STAGE_ORDER as $stage) {
            if ($counts[$stage->value] > 0) {
                $parts[] = __('social_content.notifications.reminder_digest.parts.'.$stage->value, [
                    'count' => $counts[$stage->value],
                ]);
            }
        }

        return __('social_content.notifications.reminder_digest.title', [
            'summary' => implode(__('social_content.notifications.reminder_digest.separator'), $parts),
            'total' => count($items),
            ...$counts,
        ]);
    }

    /**
     * Zil govdesi HTML olarak (temizlenerek) cizilir: basliklar kacislanir,
     * satirlar <br> ile ayrilir.
     *
     * @param  list<array{content: SocialContent, stage: SocialReminderStage, key: string}>  $items
     */
    private function body(array $items): string
    {
        $lines = [];

        foreach (array_slice($items, 0, self::MAX_TITLES) as $item) {
            $lines[] = $this->line($item['content'], $item['stage']);
        }

        $rest = count($items) - count($lines);

        if ($rest > 0) {
            $lines[] = e(__('social_content.notifications.digest_more', ['count' => $rest]));
        }

        return __('social_content.notifications.reminder_digest.body', [
            'list' => implode('<br>', $lines),
        ]);
    }

    private function line(SocialContent $content, SocialReminderStage $stage): string
    {
        $date = (string) $content->planned_on?->format('d.m.Y');

        if (filled($content->planned_time)) {
            $date .= ' '.$content->planned_time;
        }

        $line = __('social_content.notifications.digest_line', [
            'stage' => $stage->getLabel(),
            'title' => (string) $content->title,
            'date' => $date,
        ]);

        // Paylasim gunu gelmis / yarin olan ama henuz onaylanmamis icerik.
        $awaitingApproval = in_array($stage, [SocialReminderStage::Today, SocialReminderStage::Tomorrow], true)
            && $content->status !== SocialContentStatus::Approved;

        if ($awaitingApproval) {
            $line .= __('social_content.notifications.awaiting_approval_suffix');
        }

        return e($line);
    }

    /**
     * @param  list<array{content: SocialContent, stage: SocialReminderStage, key: string}>  $items
     * @return array{today: int, tomorrow: int, approaching: int, missed: int}
     */
    private function counts(array $items): array
    {
        $counts = ['today' => 0, 'tomorrow' => 0, 'approaching' => 0, 'missed' => 0];

        foreach ($items as $item) {
            $counts[$item['stage']->value]++;
        }

        return $counts;
    }

    /**
     * Ozetin rengi en acil asamaya gore: geciken varsa kirmizi, bugun varsa sari.
     *
     * @param  list<array{content: SocialContent, stage: SocialReminderStage, key: string}>  $items
     */
    private function color(array $items): string
    {
        $counts = $this->counts($items);

        return match (true) {
            $counts['missed'] > 0 => SocialReminderStage::Missed->getColor(),
            $counts['today'] > 0 => SocialReminderStage::Today->getColor(),
            default => SocialReminderStage::Tomorrow->getColor(),
        };
    }

    /**
     * "Ac" dugmesi Sosyal Medya sayfasini Plan gorunumunde acar. Konsolda ya da
     * kaynak kapaliyken adres uretilemezse bildirim dugmesiz gider.
     *
     * @return list<Action>
     */
    private function actions(): array
    {
        try {
            return [
                Action::make('open')
                    ->label(__('social_content.notifications.actions.open'))
                    ->button()
                    ->url(SocialContentResource::getUrl('index', [
                        SocialAppConfig::PARAM_VIEW => SocialAppConfig::VIEW_PLAN,
                    ], panel: 'admin'))
                    ->markAsRead(),
            ];
        } catch (Throwable) {
            return [];
        }
    }
}
