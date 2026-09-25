<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Authorization\ImpersonationService;
use BadMethodCallException;
use Filament\Http\Middleware\AuthenticateSession as FilamentAuthenticateSession;

/**
 * Filament'in oturum korumasi, personel degistirme (D-120) ile uyumlu.
 *
 * Laravel'in AuthenticateSession'i her istegin sonunda o anki kullanicinin
 * parola ozetini oturuma yazar, sonraki istekte oturum sahibinin ozetiyle
 * karsilastirir; tutmazsa oturumu kapatir. Personel degistirilmisken o anki
 * kullanici secilen personeldir. Ozet her zaman oturumun gercek sahibinden
 * (sistem hesabi) yazilmalidir; yoksa bir sonraki istekte - ornegin zilin
 * yoklamasinda - oturum kapanir (24 Eylul 2026 kullanici bildirimi: "yine
 * oturumdan atiyor").
 *
 * Livewire guncelleme isteklerinde de calisir (panelde kalici ara katman).
 */
final class AuthenticateSession extends FilamentAuthenticateSession
{
    protected function storePasswordHashInSession($request)
    {
        $impersonation = app(ImpersonationService::class);

        if (! $impersonation->isSwapped()) {
            parent::storePasswordHashInSession($request);

            return;
        }

        $operator = $impersonation->operator();

        if ($operator === null) {
            return;
        }

        $passwordHash = $operator->getAuthPassword();

        try {
            $passwordHash = $this->guard()->hashPasswordForCookie($passwordHash);
        } catch (BadMethodCallException) {
        }

        $request->session()->put([
            'password_hash_'.$this->auth->getDefaultDriver() => $passwordHash,
        ]);
    }
}
