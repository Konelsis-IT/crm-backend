<?php

declare(strict_types=1);

namespace App\Services\Authorization;

use App\Enums\Personnel\PersonnelStatus;
use App\Models\Personnel\Personnel;
use App\Models\Scopes\HideSystemAccountScope;
use App\Services\Audit\ActivityInput;
use App\Services\Audit\ActivityRecorder;
use App\Services\Support\TransactionRunner;
use Illuminate\Contracts\Session\Session;

/**
 * Personel degistirme / impersonate (D-120, 23 Eylul 2026 kullanici karari).
 *
 * Yalnizca gizli sistem hesabi baslatabilir; yetki Roller ekranindan
 * verilmez, alinamaz ve orada gorunmez (kodda sabittir). Oturum acan hesap
 * degismez: secilen personel yalnizca istek suresince yerine konur
 * (App\Http\Middleware\ImpersonatePersonnel). Bu yuzden:
 *
 *  - Arayuz secilen personelin gordugu gibi calisir (menu, yetki, veri).
 *  - Yazilan kayitlar ve hareketler sistem hesabinin adina gecer
 *    (ActorContext), ama sistem hesabi arayuzde gizli oldugu icin
 *    ekranda "Sistem" gorunur; gercek kimlik yalniz veritabanindadir.
 *
 * Baslama / bitis kaydi da sistem hesabinin hareketi olarak yazilir; bu
 * hesabin kendi karti arayuzde bulunmadigi icin kayit ekranda hicbir yerde
 * listelenmez.
 */
final class ImpersonationService
{
    public const SESSION_KEY = 'konelsis.impersonate.personnel_id';

    public function __construct(
        private readonly SystemAccount $account,
        private readonly Session $session,
        private readonly ActivityRecorder $activities,
        private readonly TransactionRunner $transactions,
    ) {}

    /** Istek suresince personel degistirildi mi (ara katman isaretler). */
    private static bool $swapped = false;

    /** Oturumu acan gercek hesap sistem hesabi mi? */
    public function isOperator(?Personnel $user): bool
    {
        return $user !== null && $this->account->is($user);
    }

    /** Gercek hesap: yalniz gizli sistem hesabi personel degistirebilir. */
    public function operator(): ?Personnel
    {
        return $this->account->personnel();
    }

    /** "Personel degistir" kutusu bu ekranda gorunsun mu? */
    public function canOperate(): bool
    {
        $user = auth()->user();

        return ($user instanceof Personnel && $this->isOperator($user)) || self::$swapped;
    }

    public function markSwapped(): void
    {
        self::$swapped = true;
    }

    public function isSwapped(): bool
    {
        return self::$swapped;
    }

    public function targetId(): ?int
    {
        $id = $this->session->get(self::SESSION_KEY);

        return is_numeric($id) ? (int) $id : null;
    }

    public function isActive(): bool
    {
        return $this->targetId() !== null;
    }

    /** Su an yerine gecilen personel (gizli hesap haric herkes secilebilir). */
    public function target(): ?Personnel
    {
        $id = $this->targetId();

        if ($id === null || $this->account->is($id)) {
            return null;
        }

        return Personnel::query()->withoutGlobalScope(HideSystemAccountScope::class)->find($id);
    }

    /**
     * Personel degistir. Yalniz sistem hesabi cagirir; gizli hesap ve
     * calisamayan personel secilemez.
     */
    public function start(Personnel $operator, int $personnelId): ?Personnel
    {
        if (! $this->isOperator($operator) || $this->account->is($personnelId)) {
            return null;
        }

        $target = Personnel::query()->find($personnelId);

        if (! $target instanceof Personnel || $target->status === PersonnelStatus::Separated) {
            return null;
        }

        $this->session->put(self::SESSION_KEY, (int) $target->getKey());
        $this->log('impersonation_started', $target);

        return $target;
    }

    /** Kendi hesabina don. */
    public function stop(?Personnel $target = null): void
    {
        $target ??= $this->target();

        if ($target instanceof Personnel) {
            $this->log('impersonation_ended', $target);
        }

        $this->session->forget(self::SESSION_KEY);
    }

    /** Hareket kaydi: aktor sistem hesabidir, bu yuzden arayuzde gorunmez. */
    private function log(string $action, Personnel $target): void
    {
        $this->transactions->run(fn () => $this->activities->record(new ActivityInput(
            subjectType: 'personnel',
            subjectId: (int) $target->getKey(),
            actionCode: 'personnel.'.$action,
            changes: ['personel' => (string) $target->full_name],
        )));
    }
}
