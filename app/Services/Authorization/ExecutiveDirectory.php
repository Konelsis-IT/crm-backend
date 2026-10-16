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

    /** Istek suresince ust yonetimdeki kisilerin kimlikleri. @var list<int>|null */
    private static ?array $ids = null;

    /** Istek suresince kisi => ust yonetim gorev kodu. @var array<int, string>|null */
    private static ?array $positions = null;

    /** Unvan kodu => ayni gorevin kodu (gorev atamasi yoksa). */
    private const TITLE_POSITIONS = ['YK_BASKANI' => 'YKB', 'IDARI_MUDUR' => 'IM'];

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

    /**
     * Ust yonetimdeki aktif kisiler (D-216: yonetim talebi onceligi siralamasi).
     * Adaylar ust yonetim departmanindakiler ve ust yonetim gorevine atananlar;
     * her biri isExecutive ile dogrulanir.
     *
     * @return list<int>
     */
    public function executiveIds(): array
    {
        if (self::$ids !== null) {
            return self::$ids;
        }

        $units = array_keys(self::POSITIONS);
        $assigned = SchemaReadiness::hasBatch('B03')
            ? PositionAssignment::query()
                ->whereNull('valid_until')
                ->whereHas('position', fn (Builder $position): Builder => $position->where(function (Builder $any): void {
                    foreach (self::POSITIONS as $unit => $codes) {
                        $any->orWhere(fn (Builder $row): Builder => $row
                            ->whereIn('code', $codes)
                            ->whereHas('orgUnit', fn (Builder $orgUnit): Builder => $orgUnit->where('code', $unit)));
                    }
                }))
                ->pluck('personnel_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all()
            : [];

        return self::$ids = Personnel::query()
            ->where(fn (Builder $candidates): Builder => $candidates
                ->whereHas('orgUnit', fn (Builder $orgUnit): Builder => $orgUnit->whereIn('code', $units))
                ->orWhereIn('id', $assigned === [] ? [0] : $assigned))
            ->get()
            ->filter(fn (Personnel $personnel): bool => $this->isExecutive($personnel))
            ->map(fn (Personnel $personnel): int => (int) $personnel->getKey())
            ->values()
            ->all();
    }

    /**
     * Ust yonetimdeki kisilerin gorev kodlari (YKB, IM; D-217: gorevin kendi is
     * kategorileri). Gorev atamasi yoksa unvandan.
     *
     * @return array<int, string>
     */
    public function positionCodes(): array
    {
        if (self::$positions !== null) {
            return self::$positions;
        }

        $codes = [];

        foreach (Personnel::query()->with('title')->whereIn('id', $this->executiveIds() ?: [0])->get() as $personnel) {
            $code = $this->assignedPositionCode((int) $personnel->getKey())
                ?? (self::TITLE_POSITIONS[(string) $personnel->title?->code] ?? null);

            if ($code !== null) {
                $codes[(int) $personnel->getKey()] = $code;
            }
        }

        return self::$positions = $codes;
    }

    /** Test ve seed sonrasi onbellegi bosaltir. */
    public static function forget(): void
    {
        self::$cache = [];
        self::$ids = null;
        self::$positions = null;
    }

    /** Kisinin gecerli ust yonetim gorevinin kodu; yoksa null. */
    private function assignedPositionCode(int $personnelId): ?string
    {
        if (! SchemaReadiness::hasBatch('B03')) {
            return null;
        }

        $assignment = PositionAssignment::query()
            ->with('position:id,code')
            ->where('personnel_id', $personnelId)
            ->whereNull('valid_until')
            ->whereHas('position', fn (Builder $position): Builder => $position->where(function (Builder $any): void {
                foreach (self::POSITIONS as $unit => $codes) {
                    $any->orWhere(fn (Builder $row): Builder => $row
                        ->whereIn('code', $codes)
                        ->whereHas('orgUnit', fn (Builder $orgUnit): Builder => $orgUnit->where('code', $unit)));
                }
            }))
            ->first();

        $code = $assignment?->position?->code;

        return is_string($code) && $code !== '' ? $code : null;
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
