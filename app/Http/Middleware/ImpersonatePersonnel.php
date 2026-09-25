<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Personnel\Personnel;
use App\Services\Audit\ActorContext;
use App\Services\Authorization\ImpersonationService;
use Closure;
use Illuminate\Http\Request;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;

/**
 * Personel degistirme (D-120): oturum sistem hesabina aitken, secilen
 * personel yalnizca bu istek suresince yerine konur. Oturum kaydi
 * degismez - "kendi hesabima don" tek satirlik oturum anahtari silmektir.
 *
 * Denetim izi degismez: hareketler ve `created_by_personnel_id` gercek
 * hesabin (sistem hesabi) kimligiyle yazilir; sistem hesabi arayuzde gizli
 * oldugu icin ekranda "Sistem" gorunur.
 */
final class ImpersonatePersonnel
{
    public function __construct(
        private readonly ImpersonationService $impersonation,
        private readonly ActorContext $actor,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $operator = $request->user();

        if (! $operator instanceof Personnel || ! $this->impersonation->isOperator($operator)) {
            return $next($request);
        }

        $target = $this->impersonation->target();

        if (! $target instanceof Personnel) {
            return $next($request);
        }

        $guard = auth()->guard();
        $resolver = $request->getUserResolver();

        // Arayuz secilen personelin gozuyle calisir...
        $guard->setUser($target);
        $request->setUserResolver(fn (): Personnel => $target);

        // ...ama kayitlar gercek hesabin adina yazilir.
        $this->actor->actAsPersonnel((int) $operator->getKey());
        $this->impersonation->markSwapped();

        // Livewire guncelleme isteginde kalici ara katmanlar bilesen
        // calismadan ONCE biter (bos yanitla); degisim istek boyunca kalmali.
        // Bu istekte AuthenticateSession calismaz, oturuma ozet yazilmaz.
        if (Livewire::isLivewireRequest()) {
            return $next($request);
        }

        try {
            return $next($request);
        } finally {
            // Yanit uretildikten sonra gercek hesap geri konur. Aksi halde
            // AuthenticateSession yanit donerken secilen personelin parola
            // ozetini oturuma yazar; bir sonraki istekte oturum sahibinin
            // (sistem hesabi) ozetiyle eslesmez ve oturum kapanirdi
            // (24 Eylul 2026 kullanici bildirimi: "oturumdan atiyor").
            $guard->setUser($operator);
            $request->setUserResolver($resolver);
        }
    }
}
