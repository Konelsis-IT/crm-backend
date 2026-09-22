<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Enums\Notification\AlertSeverity;
use App\Enums\Notification\AlertState;
use App\Exceptions\Notification\AlertNotOpenException;
use App\Models\Notification\BusinessAlert;
use App\Models\Personnel\Personnel;
use App\Query\Notification\AudienceQueries;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use App\Support\DisplayTime;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

/**
 * Kritik is uyarilari (07 SS5.4 alt kumesi, D-82): son tarih taramasi acar,
 * sahibi "gordum" der, konu kapaninca sistem kapatir. Bildirim: sahibi,
 * kritik seviyede ayrica amiri ve birim yoneticisi; sahibi yoksa system_admin.
 */
final class BusinessAlertService extends AbstractService
{
    protected string $orderBy = 'opened_at';

    protected string $orderDirection = 'desc';

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly ActorContext $actor,
        private readonly PanelNotifier $notifier,
        private readonly AudienceQueries $audiences,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * Ayni konu/seviye icin bir kez acilir; daha dusuk seviyedeki acik
     * uyari kapatilir (seviye yukseldi).
     *
     * @return array{0: BusinessAlert, 1: bool}  [uyari, yeni mi]
     */
    public function openFor(DeadlineHit $hit, AlertSeverity $severity): array
    {
        $dedupeKey = $hit->dedupeKey($severity);

        /** @var BusinessAlert|null $existing */
        $existing = BusinessAlert::query()->where('dedupe_key', $dedupeKey)->first();

        if ($existing !== null) {
            return [$existing, false];
        }

        return $this->transactions->run(function () use ($hit, $severity, $dedupeKey): array {
            BusinessAlert::query()
                ->where('trigger_code', $hit->triggerCode)
                ->where('subject_type', $hit->subjectType)
                ->where('subject_id', $hit->subjectId)
                ->whereIn('state', [AlertState::Open->value, AlertState::Acknowledged->value])
                ->get()
                ->each(fn (BusinessAlert $previous) => $this->close($previous, 'escalated'));

            /** @var BusinessAlert $alert */
            $alert = parent::create([
                'alert_no' => $this->nextAlertNo(),
                'trigger_code' => $hit->triggerCode,
                'subject_type' => $hit->subjectType,
                'subject_id' => $hit->subjectId,
                'project_id' => $hit->projectId,
                'org_unit_id' => $hit->orgUnitId,
                'severity' => $severity->value,
                'owner_personnel_id' => $hit->ownerPersonnelId,
                'title_key' => $hit->titleKey(),
                'params' => $hit->params,
                'url' => $hit->url,
                'due_at' => $hit->dueAt,
                'state' => AlertState::Open->value,
                'opened_at' => Carbon::now('UTC'),
                'dedupe_key' => $dedupeKey,
            ]);

            return [$alert, true];
        });
    }

    public function acknowledge(Model|int|string $record): BusinessAlert
    {
        return $this->transactions->run(function () use ($record): BusinessAlert {
            /** @var BusinessAlert $alert */
            $alert = $this->lockForUpdate($record);

            if ($alert->state !== AlertState::Open) {
                throw AlertNotOpenException::make();
            }

            $alert->fill([
                'state' => AlertState::Acknowledged->value,
                'acknowledged_at' => Carbon::now('UTC'),
                'acknowledged_by_personnel_id' => $this->actor->personnelId(),
            ])->save();

            $this->recordActivity($alert, 'acknowledged', ['uyari_no' => $alert->alert_no]);

            return $alert;
        });
    }

    public function close(Model|int|string $record, string $reason): BusinessAlert
    {
        return $this->transactions->run(function () use ($record, $reason): BusinessAlert {
            /** @var BusinessAlert $alert */
            $alert = $this->lockForUpdate($record);

            if (! $alert->isOpen()) {
                return $alert;
            }

            $alert->fill([
                'state' => $reason === 'resolved' ? AlertState::Resolved->value : AlertState::Closed->value,
                'closed_at' => Carbon::now('UTC'),
            ])->save();

            $this->recordActivity($alert, 'closed', ['uyari_no' => $alert->alert_no, 'neden' => $reason]);

            return $alert;
        });
    }

    /** Uyariyi ilgililere bildirir; gonderilen sayisini doner. */
    public function notify(BusinessAlert $alert): int
    {
        $recipients = collect();
        $owner = $alert->owner;

        if ($owner instanceof Personnel && $owner->isActive()) {
            $recipients->push($owner);

            if ($alert->severity === AlertSeverity::Critical) {
                $manager = $owner->currentManager();

                if ($manager instanceof Personnel && $manager->isActive()) {
                    $recipients->push($manager);
                }

                $unitManager = $owner->orgUnit?->manager;

                if ($unitManager instanceof Personnel && $unitManager->isActive()) {
                    $recipients->push($unitManager);
                }
            }
        }

        if ($recipients->isEmpty()) {
            $recipients = $this->audiences->systemAdmins();
        }

        $recipients = $recipients->unique(fn (Personnel $personnel) => $personnel->getKey());
        $sent = 0;

        foreach ($recipients as $personnel) {
            $sent += $this->notifier->send(
                [$personnel],
                __('business_alert.notifications.title.'.$alert->severity->value),
                __('business_alert.notifications.body', [
                    'subject' => $alert->title(),
                    'date' => $alert->due_at?->timezone(DisplayTime::zone())->format('d.m.Y') ?? '-',
                ]),
                $alert->severity->getIcon(),
                $alert->severity->getColor(),
                $this->actionsFor($alert, $personnel),
            );
        }

        return $sent;
    }

    /**
     * @return list<Action>
     */
    private function actionsFor(BusinessAlert $alert, Personnel $personnel): array
    {
        $actions = [];

        if (filled($alert->url)) {
            $actions[] = Action::make('open')
                ->label(__('business_alert.actions.open'))
                ->button()
                ->url((string) $alert->url);
        }

        try {
            $actions[] = Action::make('acknowledge')
                ->label(__('business_alert.actions.acknowledge'))
                ->button()
                ->color('gray')
                ->url(URL::temporarySignedRoute(
                    'filament.admin.notifications.alert-acknowledge',
                    Carbon::now()->addDays(14),
                    ['alert' => $alert->getKey(), 'p' => $personnel->getKey()],
                ))
                ->markAsRead();
        } catch (Throwable) {
            // Rota kayitli degilse yalniz "Ac" kalir.
        }

        return $actions;
    }

    private function nextAlertNo(): string
    {
        do {
            $candidate = 'UYR-'.Carbon::now('UTC')->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (BusinessAlert::query()->where('alert_no', $candidate)->exists());

        return $candidate;
    }
}
