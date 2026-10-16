<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Enums\Notification\AnnouncementAudience;
use App\Exceptions\ActorRequiredException;
use App\Exceptions\Notification\NoRecipientsException;
use App\Exceptions\Notification\NotificationScopeNotAllowedException;
use App\Models\Notification\PanelNotification;
use App\Models\Personnel\OrgUnit;
use App\Models\Personnel\Personnel;
use App\Models\Scopes\HideSystemAccountScope;
use App\Query\Notification\WarningTargetQueries;
use App\Services\AbstractService;
use App\Services\Audit\ActivityInput;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Authorization\SystemAccount;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;

/**
 * Yoneticinin panodan gonderdigi acil uyarilar (D-208, kullanici 14 Ekim
 * 2026): "acil koduyla ilgili is icin ilgili baglantili olan personel ve
 * departmanlara bildirim gonderebilir ... Durumu acil olarak isaretlendi
 * yonetici tarafindan gibi sabit bir ifade olabilir. Hemen uyar, ozel bildirim
 * gibi alan olabilir ... Departman basligi yaninda ilgili departmani cagir,
 * ilgili departmana uyari gonder diye 2 ayri yine buton olabilir."
 *
 * Teslimat Filament zilidir (D-49, PanelNotifier); kayit Personel
 * Hareketleri'ne yazilir: uyarida kaydin kendisine
 * (`{tur}.urgent_warned`), departman cagrisi ve uyarisinda departmana
 * (`org_unit.called` / `org_unit.alerted`). Kisiye gondermek "notify:personnel",
 * departmana gondermek "notify:department" izni ister (duyurudaki kurallar).
 * Otomatik karsiligi sabah son tarih taramasidir (DeadlineScanner).
 */
final class UrgentWarningService extends AbstractService
{
    protected string $model = PanelNotification::class;

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly ActorContext $actor,
        private readonly AudienceResolver $audiences,
        private readonly PanelNotifier $notifier,
        private readonly SystemAccount $systemAccount,
        private readonly WarningTargetQueries $targets,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /** Gonderen kisiye ya da departmana uyari gonderebilir mi? */
    public function canWarn(?Personnel $sender, bool $departments = false): bool
    {
        if (! $sender instanceof Personnel) {
            return false;
        }

        return $this->audiences->permits($sender, $departments ? AnnouncementAudience::Department : AnnouncementAudience::Personnel);
    }

    /**
     * Kayit icin acil uyarisi. Mesaj bossa sabit ifade ("Durumu ... tarafindan
     * acil olarak isaretlendi") gider. Alicilar verilmezse kaydin varsayilan
     * ilgilileri kullanilir.
     *
     * @param  list<int>|null  $personnelIds
     * @param  list<int>|null  $unitIds
     */
    public function warn(WarningTarget $target, ?string $message = null, ?array $personnelIds = null, ?array $unitIds = null): int
    {
        $sender = $this->sender();
        $personnelIds = array_values(array_map('intval', $personnelIds ?? $target->personnelIds));
        $unitIds = array_values(array_map('intval', $unitIds ?? $target->unitIds));

        if (($personnelIds !== [] && ! $this->canWarn($sender)) || ($unitIds !== [] && ! $this->canWarn($sender, true))) {
            throw NotificationScopeNotAllowedException::make();
        }

        $recipients = $this->recipientsOrFail($personnelIds, $unitIds, $sender);
        $message = filled($message) ? trim((string) $message) : null;
        $senderName = $this->senderName($sender);

        return $this->transactions->run(function () use ($target, $message, $recipients, $senderName, $personnelIds, $unitIds): int {
            $this->activities->record(new ActivityInput(
                subjectType: $target->subjectType,
                subjectId: $target->subjectId,
                actionCode: $target->subjectType.'.urgent_warned',
                changes: [
                    'konu' => $target->title,
                    'alici_sayisi' => $recipients->count(),
                    'departman_sayisi' => count($unitIds),
                    'kisi_sayisi' => count($personnelIds),
                    'mesaj' => $message,
                ],
            ));

            return $this->notifier->send(
                $recipients,
                __('urgent_warning.item.title', ['subject' => $target->title]),
                $message === null
                    ? __('urgent_warning.item.body_fixed', ['sender' => $senderName])
                    : __('urgent_warning.item.body_custom', ['sender' => $senderName, 'message' => $message]),
                Heroicon::OutlinedExclamationTriangle,
                'danger',
                $this->openAction($target->url, __('urgent_warning.actions.open_record')),
            );
        });
    }

    /** "Departmani cagir": departmanin etkin personeline yoneticinin cagrisi. */
    public function callDepartment(OrgUnit $unit, ?string $note = null): int
    {
        $sender = $this->departmentSender();
        $recipients = $this->recipientsOrFail([], [(int) $unit->getKey()], $sender);
        $note = filled($note) ? trim((string) $note) : null;
        $senderName = $this->senderName($sender);

        return $this->transactions->run(function () use ($unit, $note, $recipients, $senderName): int {
            $this->activities->record(new ActivityInput(
                subjectType: 'org_unit',
                subjectId: (int) $unit->getKey(),
                actionCode: 'org_unit.called',
                changes: ['departman' => $unit->name, 'alici_sayisi' => $recipients->count(), 'not' => $note],
            ));

            $body = __('urgent_warning.call.body', ['sender' => $senderName, 'department' => $unit->name]);

            return $this->notifier->send(
                $recipients,
                __('urgent_warning.call.title', ['sender' => $senderName]),
                $note === null ? $body : $body.' '.__('urgent_warning.note', ['note' => $note]),
                Heroicon::OutlinedPhoneArrowUpRight,
                'warning',
            );
        });
    }

    /**
     * "Departmana uyari gonder": bekleyen islerin ozeti (konu, kayit sayisi,
     * en eski bekleme) ile departmanin etkin personeline uyari.
     */
    public function alertDepartment(OrgUnit $unit, string $topic, int $count, ?int $oldestDays, ?string $url, ?string $note = null): int
    {
        $sender = $this->departmentSender();
        $recipients = $this->recipientsOrFail([], [(int) $unit->getKey()], $sender);
        $note = filled($note) ? trim((string) $note) : null;
        $senderName = $this->senderName($sender);

        return $this->transactions->run(function () use ($unit, $topic, $count, $oldestDays, $url, $note, $recipients, $senderName): int {
            $this->activities->record(new ActivityInput(
                subjectType: 'org_unit',
                subjectId: (int) $unit->getKey(),
                actionCode: 'org_unit.alerted',
                changes: ['departman' => $unit->name, 'konu' => $topic, 'kayit_sayisi' => $count, 'alici_sayisi' => $recipients->count(), 'not' => $note],
            ));

            $body = $oldestDays !== null && $oldestDays > 0
                ? __('urgent_warning.department.body_oldest', ['topic' => $topic, 'count' => $count, 'days' => $oldestDays, 'sender' => $senderName])
                : __('urgent_warning.department.body', ['topic' => $topic, 'count' => $count, 'sender' => $senderName]);

            return $this->notifier->send(
                $recipients,
                __('urgent_warning.department.title', ['department' => $unit->name]),
                $note === null ? $body : $body.' '.__('urgent_warning.note', ['note' => $note]),
                Heroicon::OutlinedBellAlert,
                'warning',
                $this->openAction($url, __('urgent_warning.actions.open_list')),
            );
        });
    }

    private function sender(): Personnel
    {
        $senderId = $this->actor->personnelId();

        // Personel degistirmede (D-120) gizli hesap kapsam disinda okunur (AnnouncementService gibi).
        $sender = $senderId !== null
            ? Personnel::query()->withoutGlobalScope(HideSystemAccountScope::class)->find($senderId)
            : null;

        if (! $sender instanceof Personnel) {
            throw ActorRequiredException::make();
        }

        return $sender;
    }

    private function departmentSender(): Personnel
    {
        $sender = $this->sender();

        if (! $this->canWarn($sender, true)) {
            throw NotificationScopeNotAllowedException::make();
        }

        return $sender;
    }

    /**
     * @param  list<int>  $personnelIds
     * @param  list<int>  $unitIds
     * @return Collection<int, Personnel>
     */
    private function recipientsOrFail(array $personnelIds, array $unitIds, Personnel $sender): Collection
    {
        $recipients = $this->targets->recipients($personnelIds, $unitIds, (int) $sender->getKey());

        if ($recipients->isEmpty()) {
            throw NoRecipientsException::make();
        }

        return $recipients;
    }

    private function senderName(Personnel $sender): string
    {
        // Gizli hesabin adi arayuzde hic gorunmez (D-120): gonderen "Sistem".
        return $this->systemAccount->is($sender) ? (string) __('activity.system') : (string) $sender->full_name;
    }

    /**
     * @return list<Action>
     */
    private function openAction(?string $url, string $label): array
    {
        if (blank($url)) {
            return [];
        }

        return [
            Action::make('open')
                ->label($label)
                ->button()
                ->url((string) $url)
                ->markAsRead(),
        ];
    }
}
