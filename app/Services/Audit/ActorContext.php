<?php

declare(strict_types=1);

namespace App\Services\Audit;

use Illuminate\Contracts\Auth\Factory as AuthFactory;

/**
 * Istek/is basina hareketi kimin yaptigini tutar.
 *
 * Insan islemlerinde oturum acmis personel doldurulur. Uygulamanin kendi
 * olusturdugu kayitlarda personel bos kalir ve arayuzde "Sistem" gorunur.
 */
final class ActorContext
{
    private ?int $personnelId = null;

    private bool $resolvedFromAuth = false;

    public function __construct(private readonly AuthFactory $auth) {}

    /** Oturum acmis personelin kimligi; sistem isleminde null. */
    public function personnelId(): ?int
    {
        if ($this->personnelId !== null) {
            return $this->personnelId;
        }

        if (! $this->resolvedFromAuth) {
            $this->resolvedFromAuth = true;
            $user = $this->auth->guard()->user();

            if ($user !== null) {
                $this->personnelId = (int) $user->getAuthIdentifier();
            }
        }

        return $this->personnelId;
    }

    public function actAsPersonnel(int $personnelId): void
    {
        $this->personnelId = $personnelId;
        $this->resolvedFromAuth = true;
    }

    /**
     * Personel bagi olmadan, sistem adina calisir; sonra onceki duruma doner.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function runAsSystem(callable $callback): mixed
    {
        $previous = [$this->personnelId, $this->resolvedFromAuth];

        try {
            $this->personnelId = null;
            $this->resolvedFromAuth = true;

            return $callback();
        } finally {
            [$this->personnelId, $this->resolvedFromAuth] = $previous;
        }
    }

    public function reset(): void
    {
        $this->personnelId = null;
        $this->resolvedFromAuth = false;
    }
}
