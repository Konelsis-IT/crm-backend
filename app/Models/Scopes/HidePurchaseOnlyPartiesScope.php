<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Services\Platform\SchemaReadiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Yalniz satin alma tedarikcisi olan firmalar (D-232, B58) Taraflar tarafinda
 * gorunmez: Taraflar listesi, taraf secim kutulari, genel arama, iliskiler.
 * Kullanici 21 Ekim 2026: "Taraflar kısmındaki tedarikçiler ile buradakini
 * birbirine karıştırmayalım ... oraya eklemeye ... çalışma."
 *
 * Asan yerler (withoutGlobalScope): Tedarikciler ekrani, satin alma
 * kayitlarinin tedarikci iliskisi ve secimleri, taraf servisleri (kimligi belli
 * kayit uzerinde calisirlar), birlestirme. "Ayni firma" onayli tedarikci
 * (`purchase_only` = 0) Taraflar'da da gorunur.
 */
final class HidePurchaseOnlyPartiesScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! SchemaReadiness::hasBatch('B58')) {
            return;
        }

        $builder->whereNotExists(function ($query) use ($model): void {
            $query->selectRaw('1')
                ->from('purchase_suppliers')
                ->whereColumn('purchase_suppliers.party_id', $model->getQualifiedKeyName())
                ->where('purchase_suppliers.purchase_only', true);
        });
    }
}
