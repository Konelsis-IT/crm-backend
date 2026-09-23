<?php

declare(strict_types=1);

namespace App\Services\Authorization;

use App\Models\Personnel\Personnel;
use App\Models\Personnel\PositionAssignment;
use App\Services\Platform\SchemaReadiness;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ust yonetim (D-116, 23 Eylul 2026, kullanici karari): "Yoneticiler =
 * Yonetim kurulu baskani ve Idari mudur ... tabi personel ismine gore degil,
 * pozisyona & organizasyon birimine gore yetkilenecektir."
 *
 * Bu yuzden kodda kisi adi yoktur: bir kisi, listedeki departmanin
 * (organizasyon birimi) altindaki ust yonetim gorevine (pozisyon) atanmissa
 * yoneticidir. Gorev atamasi yoksa ayni gorevin unvani (B26) yedek yoldur;
 * unvan da departmanla birlikte aranir.
 *
 * Kimler gorur: Analizler menusu, personel kartindaki Haftalik kontrol ve
 * Personel Hareketleri sekmeleri, kontrol matrisinin salt okunur hali.
 * Matrisi doldurmak ust yonetimin isi degildir (IK doldurur).
 */
final class ExecutiveDirectory
{
    /** Departman kodu => ust yonetim gorev (pozisyon) kodlari. */
    private const POSITIONS = [
        'YONETIM' => ['YKB', 'IM'],
    ];

    /** Ayni gorevlerin unvan kodlari (gorev atamasi bulunamazsa). */
    private const TITLES = ['YK_BASKANI', 'IDARI_MUDUR'];

    /** Istek suresince personel basina sonuc. @var array<int, bool> */
    private static array $cache = [];

    public function isExecutive(?Personnel $personnel): bool
    {
        if (! $personnel instanceof Personnel || ! $personnel->isActive()) {
            return false;
        }

        $id = (int) $personnel->getKey();

        return self::$cache[$id] ??= $this->byPosition($id) || $this->byTitle($personnel);
    }

    /** Ust yonetim departmanlarinin kodlari. @return list<string> */
    public function unitCodes(): array
    {
        return array_keys(self::POSITIONS);
    }

    /** Test ve seed sonrasi onbellegi bosaltir. */
    public static function forget(): void
    {
        self::$cache = [];
    }

    /** Gecerli (kapanmamis) gorev atamasi ust yonetim kadrosunda mi? */
    private function byPosition(int $personnelId): bool
    {
        if (! SchemaReadiness::hasBatch('B03')) {
            return false;
        }

        return PositionAssignment::query()
            ->where('personnel_id', $personnelId)
            ->whereNull('valid_until')
            ->whereHas('position', fn (Builder $position): Builder => $position->where(function (Builder $any): void {
                foreach (self::POSITIONS as $unit => $codes) {
                    $any->orWhere(fn (Builder $row): Builder => $row
                        ->whereIn('code', $codes)
                        ->whereHas('orgUnit', fn (Builder $orgUnit): Builder => $orgUnit->where('code', $unit)));
                }
            }))
            ->exists();
    }

    /** Gorev atamasi yoksa: ust yonetim departmanindaki ust yonetim unvani. */
    private function byTitle(Personnel $personnel): bool
    {
        $unit = (string) $personnel->loadMissing('orgUnit')->orgUnit?->code;

        if (! array_key_exists($unit, self::POSITIONS)) {
            return false;
        }

        return in_array((string) $personnel->loadMissing('title')->title?->code, self::TITLES, true);
    }
}
