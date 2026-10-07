<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

use App\Models\Acquisition\BusinessCodeSequence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use RuntimeException;

/**
 * Seed korumasi (D-165, 6 Ekim 2026 kullanici talimati: "yapilan veriler
 * silinirse, guncellenirse, uzerine yazilirsa ben bu sistemi nasil
 * gelistirecegim? ... teklifler, sozlesmeler, ihaleler, potansiyel isler,
 * gorusme planlari, taraflar ne olursa db:seed'e bunun icin kontrol koymamiz
 * lazim").
 *
 * Bir ProtectedSeeder calisirken var olan bir kaydin guncellenmesi, silinmesi
 * ya da geri alinmasi durdurulur (istisna atilir, seed durur). Seed yalniz
 * yeni kayit ekler; bu calismada kendi ekledigi kaydi tamamlayabilir.
 * Model olaylariyla calisir; dogrudan sorgu (DB::table()->update) kullanan
 * seeder yazilmaz.
 */
final class SeedGuard
{
    private static int $depth = 0;

    private static bool $listening = false;

    /**
     * Kullanici verisi olmayan sayac tablolari: yeni kayit kodu alinirken
     * (POTIS / TKLF / PRJ) sayac satiri ilerler; bu guncelleme serbesttir.
     *
     * @var list<class-string<Model>>
     */
    private const COUNTERS = [BusinessCodeSequence::class];

    /** @var array<string, true> bu surecte seed sirasinda eklenen kayitlar */
    private static array $created = [];

    public static function enter(): void
    {
        self::listen();
        self::$depth++;
    }

    public static function leave(): void
    {
        self::$depth = max(0, self::$depth - 1);
    }

    public static function active(): bool
    {
        return self::$depth > 0;
    }

    /** @var int acik guncelleme izinleri (allowingUpdates) */
    private static int $allowUpdates = 0;

    /**
     * Kullanicinin acikca istedigi guncelleme icin dar izin (D-169, 7 Ekim 2026:
     * "bazi belgeler bir sonraki asamaya gecmis olabilir, o durumlarda da
     * guncelleme yapacagiz ama daha once kayitli bilgileri kesinlikle silmiyor,
     * goz ardi etmiyoruz"). Yalniz verilen islem suresince var olan kayit
     * guncellenebilir (durumu ileri almak, ozete satir eklemek); silme ve geri
     * alma yine durdurulur. Seeder bu izni yalniz ileri giden, veri kaybettirmeyen
     * islemler icin kullanir.
     *
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     */
    public static function allowingUpdates(\Closure $callback): mixed
    {
        self::$allowUpdates++;

        try {
            return $callback();
        } finally {
            self::$allowUpdates--;
        }
    }

    private static function listen(): void
    {
        if (self::$listening) {
            return;
        }

        self::$listening = true;

        Event::listen('eloquent.created: *', static function (string $event, array $payload): void {
            $model = $payload[0] ?? null;

            if (self::$depth > 0 && $model instanceof Model) {
                self::$created[self::key($model)] = true;
            }
        });

        foreach (['updating' => 'guncellenmek', 'deleting' => 'silinmek', 'restoring' => 'geri alinmak'] as $action => $verb) {
            Event::listen('eloquent.'.$action.': *', static function (string $event, array $payload) use ($action, $verb): void {
                $model = $payload[0] ?? null;

                if (self::$depth === 0 || ! $model instanceof Model || in_array($model::class, self::COUNTERS, true) || self::createdInProcess($model)) {
                    return;
                }

                // D-169: dar guncelleme izni yalniz guncellemeyi acar; silme / geri alma her zaman durur.
                if ($action === 'updating' && self::$allowUpdates > 0) {
                    return;
                }

                throw new RuntimeException(sprintf(
                    'Seed korumasi (D-165): var olan %s #%s kaydi seed sirasinda %s istendi; var olan veri degistirilemez. Seed durduruldu.',
                    class_basename($model),
                    (string) $model->getKey(),
                    $verb,
                ));
            });
        }
    }

    /**
     * Kayit bu surecte seed tarafindan mi eklendi. Eklenmediyse kayit ve
     * altindakiler (teklif, not, kisi, kanal ...) canli sisteme aittir: seed
     * altina da bir sey eklemez (D-165; silinmis alt kayit geri gelmesin).
     */
    public static function createdInProcess(Model $model): bool
    {
        return isset(self::$created[self::key($model)]);
    }

    private static function key(Model $model): string
    {
        return $model::class.'#'.(string) $model->getKey();
    }
}
