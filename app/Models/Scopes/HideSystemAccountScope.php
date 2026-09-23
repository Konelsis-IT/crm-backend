<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Services\Authorization\SystemAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Gizli sistem hesabini her sorgudan dusurur (D-120): personel listeleri,
 * secim kutulari, iliskiler ("yazan", "islem yapan", "sorumlu") ve gecmis
 * dokumleri bu hesabi hic gormez. Hesap yalnizca kendisi oturum acmisken
 * gorunur; boylece kendi profiline ve "personel degistir" kutusuna erisir.
 *
 * Kimlik dogrulama akisi etkilenmez: oturum henuz cozulmemisken (hasUser()
 * false) kapsam uygulanmaz, aksi halde kullanici hicbir zaman yuklenemezdi.
 * Kapsami asmak gerektiginde `withoutGlobalScope(HideSystemAccountScope::class)`
 * kullanilir (impersonate servisi ve SystemAccount).
 */
final class HideSystemAccountScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $account = app(SystemAccount::class);
        $id = $account->id();

        if ($id === null) {
            return;
        }

        $guard = auth()->guard();

        // Oturum henuz cozulmediyse (giris, "beni hatirla", kuyruk isleri)
        // dokunma; yoksa kullaniciyi yukleyen sorgu da suzulurdu.
        if (! method_exists($guard, 'hasUser') || ! $guard->hasUser()) {
            return;
        }

        if ($account->is($guard->user())) {
            return;
        }

        $builder->where($model->getQualifiedKeyName(), '!=', $id);
    }
}
