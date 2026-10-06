<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

/**
 * Korumali seeder tabani (D-165). Ozellik katalogu (FeatureSeeder) disindaki
 * butun seeder'lar bundan turer:
 *
 * - Calisirken SeedGuard aciktir: var olan kayit guncellenemez, silinemez.
 * - Her veri satiri row() icinde, sabit bir anahtarla yazilir. Satir seed
 *   arsivindeyse (B45) hic islenmez; islenince arsive dusulur. Boylece
 *   canlida silinen ya da degistirilen kayit seed ile geri gelmez; seed
 *   dosyasina sonradan eklenen satir yazilir.
 * - Yeni bir seed isi yeni bir seeder dosyasidir; var olan seeder'in
 *   satirlari degistirilerek canli veri duzeltilmez.
 */
abstract class ProtectedSeeder extends Seeder
{
    private int $archivedRows = 0;

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function __invoke(array $parameters = [])
    {
        SeedGuard::enter();

        try {
            $result = parent::__invoke($parameters);
        } finally {
            SeedGuard::leave();
        }

        if ($this->archivedRows > 0) {
            $this->command?->info(sprintf('%s: %d satir seed arsivinde, atlandi.', class_basename(static::class), $this->archivedRows));
        }

        return $result;
    }

    /**
     * Seed veri satiri. $write yazilan (ya da zaten var olup benimsenen)
     * kaydi, kayitsiz yazildiysa true doner; null / false "yazilmadi, sonra
     * yeniden denenir" demektir ve arsive dusulmez.
     *
     * @template T
     *
     * @param  Closure(): T  $write
     * @return T|null
     */
    protected function row(string $key, Closure $write): mixed
    {
        $archive = SeedArchive::instance();

        if ($archive->has(static::class, $key)) {
            $this->archivedRows++;

            return null;
        }

        $result = $write();

        if ($result !== null && $result !== false) {
            $archive->record(static::class, $key, $result instanceof Model ? $result : null);
        }

        return $result;
    }

    protected function isArchived(string $key): bool
    {
        return SeedArchive::instance()->has(static::class, $key);
    }

    /**
     * Kayit bu calismada seed ile mi eklendi. Degilse (canlida zaten vardi)
     * seed onun altina teklif, not, kisi, kanal gibi alt kayit eklemez.
     */
    protected function ownedBySeed(Model $model): bool
    {
        return SeedGuard::createdInProcess($model);
    }
}
